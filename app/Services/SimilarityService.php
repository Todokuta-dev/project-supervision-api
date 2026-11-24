<?php

namespace App\Services;

use App\Models\RepositoryItem;
use App\Models\Submission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class SimilarityService
{
    private const SHINGLE_SIZE = 5;

    private const STOP_WORDS = [
        'the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'her', 'was',
        'one', 'our', 'out', 'day', 'get', 'has', 'him', 'his', 'how', 'new', 'now',
        'old', 'see', 'two', 'way', 'who', 'did', 'its', 'let', 'say', 'she', 'too',
        'use', 'that', 'this', 'with', 'from', 'have', 'will', 'your', 'they', 'been',
        'were', 'said', 'each', 'which', 'their', 'time', 'into', 'than', 'more',
        'other', 'also', 'these', 'some', 'such', 'when', 'what', 'where', 'while',
        'during', 'through', 'using', 'used', 'based', 'within', 'without', 'among',
        'chapter', 'system', 'study', 'research', 'project', 'paper', 'thesis',
        'dissertation', 'university', 'department', 'student', 'supervisor', 'abstract',
        'introduction', 'methodology', 'method', 'methods', 'result', 'results',
        'discussion', 'conclusion', 'objective', 'objectives', 'background',
        'reference', 'references', 'figure', 'table', 'author', 'authors',
    ];

    /** Extract readable text. An empty result is treated as an extraction failure. */
    public function extractText(string $storagePath): string
    {
        try {
            $disk = Storage::disk('public');
            $fullPath = $disk->path($storagePath);

            if (! is_file($fullPath)) {
                throw new \RuntimeException('Stored submission file was not found.');
            }

            $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

            // Apache Tika handles formats beyond PHPWord/PDFParser and can run
            // OCR when the Tika server is configured with its OCR strategy.
            $tikaUrl = rtrim((string) config('services.tika.url'), '/');
            if ($tikaUrl !== '') {
                try {
                    $response = Http::timeout(90)
                        ->withBody(file_get_contents($fullPath), 'application/octet-stream')
                        ->put($tikaUrl.'/tika');
                    if ($response->successful() && trim($response->body()) !== '') {
                        return trim($response->body());
                    }
                    Log::warning('SimilarityService: Tika did not extract text.', [
                        'storage_path' => $storagePath,
                        'status' => $response->status(),
                    ]);
                } catch (\Throwable $exception) {
                    Log::warning('SimilarityService: Tika request failed.', [
                        'storage_path' => $storagePath,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }

            if ($extension === 'txt') {
                return trim((string) file_get_contents($fullPath));
            }

            if ($extension === 'pdf') {
                $pdf = (new PdfParser())->parseFile($fullPath);

                return trim($pdf->getText());
            }

            if (in_array($extension, ['doc', 'docx'], true)) {
                $document = IOFactory::load($fullPath);
                $text = '';

                foreach ($document->getSections() as $section) {
                    $text .= $this->collectWordText($section->getElements());
                }

                return trim($text);
            }
        } catch (\Throwable $exception) {
            Log::warning('SimilarityService: text extraction failed.', [
                'storage_path' => $storagePath,
                'exception' => $exception->getMessage(),
            ]);
        }

        return '';
    }

    private function collectWordText(array $elements): string
    {
        $text = '';

        foreach ($elements as $element) {
            if (method_exists($element, 'getText')) {
                $value = $element->getText();
                if (is_string($value) && $value !== '') {
                    $text .= $value.' ';
                }
            }

            if (method_exists($element, 'getElements')) {
                $text .= $this->collectWordText($element->getElements());
            }
        }

        return $text;
    }

    public function extractKeywords(string $text, int $limit = 8): array
    {
        $frequency = array_count_values(array_column($this->tokens($text), 'term'));
        arsort($frequency);

        return array_slice(array_keys($frequency), 0, $limit);
    }

    public function analysisStatus(string $text): string
    {
        if (trim($text) === '') {
            return 'extraction_failed';
        }

        return count($this->meaningfulTokens($this->tokens($text))) < self::SHINGLE_SIZE
            ? 'insufficient_text'
            : 'complete';
    }

    /** Compare a submission against archived repository items. */
    public function compareAgainstRepository(string $submissionText): array
    {
        $items = RepositoryItem::query()
            ->whereNotNull('full_text')
            ->where('full_text', '!=', '')
            ->get(['repo_id', 'title', 'full_text']);

        $sources = $items->map(fn (RepositoryItem $item) => [
            'source_title' => $item->title,
            'source_id' => $item->repo_id,
            'text' => $item->full_text,
        ])->all();

        $matches = $this->compareDocuments($submissionText, $sources);

        return [
            'score' => $matches[0]['matched_percentage'] ?? 0,
            'matched_sources' => $matches,
        ];
    }

    /** Compare submissions of the same type and chapter, excluding this project. */
    public function compareAgainstSameStage(
        string $submissionText,
        string $submissionType,
        ?string $chapterLabel,
        string $excludeProjectId
    ): array {
        if (trim($submissionText) === '') {
            return [];
        }

        if ($submissionType === 'chapter_draft' && blank($chapterLabel)) {
            return [];
        }

        $query = Submission::query()
            ->where('submission_type', $submissionType)
            ->where('project_id', '!=', $excludeProjectId)
            ->whereNotNull('full_text')
            ->where('full_text', '!=', '');

        if ($submissionType === 'chapter_draft') {
            $query->where('chapter_label', $chapterLabel);
        }

        $submissions = $query->with('project:project_id,title')->get([
            'submission_id',
            'project_id',
            'full_text',
        ]);

        $sources = $submissions->map(fn (Submission $submission) => [
            'source_title' => $submission->project?->title ?? 'Unknown Project',
            'source_id' => $submission->submission_id,
            'text' => $submission->full_text,
        ])->all();

        return $this->compareDocuments($submissionText, $sources);
    }

    private function compareDocuments(string $submissionText, array $sources): array
    {
        $targetTokens = $this->tokens($submissionText);
        $targetFeatures = $this->meaningfulTokens($targetTokens);
        if (count($targetFeatures) < self::SHINGLE_SIZE || $sources === []) {
            return [];
        }

        $sourceTokens = array_map(fn (array $source) => $this->tokens($source['text']), $sources);
        $sourceFeatures = array_map(fn (array $tokens) => $this->meaningfulTokens($tokens), $sourceTokens);
        $documentFrequencies = $this->documentFrequencies(array_merge([$targetFeatures], $sourceFeatures));
        $documentCount = count($sourceTokens) + 1;
        $targetVector = $this->tfIdfVector($targetFeatures, $documentFrequencies, $documentCount);
        $targetShingles = $this->shingles($targetTokens);
        $matches = [];

        foreach ($sources as $index => $source) {
            $tokens = $sourceTokens[$index];
            $features = $sourceFeatures[$index];
            if (count($features) < self::SHINGLE_SIZE) {
                continue;
            }

            $sourceShingles = $this->shingles($tokens);
            $sharedShingles = array_intersect_key($targetShingles, $sourceShingles);
            $sharedShingleCount = count($sharedShingles);
            $targetMinShingleCount = min(count($targetShingles), count($sourceShingles));
            $phraseOverlap = $targetMinShingleCount > 0
                ? $sharedShingleCount / $targetMinShingleCount
                : 0.0;

            $tfIdfSimilarity = $this->cosineSimilarity(
                $targetVector,
                $this->tfIdfVector($features, $documentFrequencies, $documentCount)
            ) * 100;

            // Phrase overlap finds copied passages; TF-IDF helps rank related documents.
            $score = round(($phraseOverlap * 70) + ($tfIdfSimilarity * 0.30), 2);
            if ($score < 8 || ($sharedShingleCount === 0 && $tfIdfSimilarity < 25)) {
                continue;
            }

            $passages = $this->matchingPassages(
                $submissionText,
                $source['text'],
                $targetTokens,
                $tokens,
                $sharedShingles,
                $sourceShingles
            );

            $matches[] = [
                'source_title' => $source['source_title'],
                'source_id' => $source['source_id'],
                'matched_percentage' => $score,
                'tfidf_similarity' => round($tfIdfSimilarity, 2),
                'phrase_overlap' => round($phraseOverlap * 100, 2),
                'shared_phrase_count' => $sharedShingleCount,
                'matched_by' => $sharedShingleCount > 0 ? 'phrase_and_vocabulary' : 'vocabulary',
                'matched_excerpt' => $passages[0]['submission_excerpt'] ?? $this->excerptAroundSharedTerm(
                    $submissionText,
                    $targetTokens,
                    $tokens
                ),
                'source_excerpt' => $passages[0]['source_excerpt'] ?? $this->excerptAroundSharedTerm(
                    $source['text'],
                    $tokens,
                    $targetTokens
                ),
                'matched_passages' => $passages,
            ];
        }

        usort($matches, fn ($left, $right) => $right['matched_percentage'] <=> $left['matched_percentage']);

        return array_slice($matches, 0, 5);
    }

    private function tokens(string $text): array
    {
        $matched = preg_match_all('/[\p{L}\p{N}]{3,}/u', $text, $matches, PREG_OFFSET_CAPTURE);
        if ($matched === false) {
            return [];
        }

        $stopWords = array_fill_keys(self::STOP_WORDS, true);
        $tokens = [];

        foreach ($matches[0] as [$value, $offset]) {
            $term = mb_strtolower($value, 'UTF-8');
            $tokens[] = [
                'term' => $term,
                'offset' => $offset,
                'meaningful' => ! isset($stopWords[$term]) && ! preg_match('/^\d+$/', $term),
            ];
        }

        return $tokens;
    }

    private function meaningfulTokens(array $tokens): array
    {
        return array_values(array_filter($tokens, fn (array $token) => $token['meaningful']));
    }

    private function documentFrequencies(array $documents): array
    {
        $frequencies = [];

        foreach ($documents as $tokens) {
            foreach (array_unique(array_column($tokens, 'term')) as $term) {
                $frequencies[$term] = ($frequencies[$term] ?? 0) + 1;
            }
        }

        return $frequencies;
    }

    private function tfIdfVector(array $tokens, array $documentFrequencies, int $documentCount): array
    {
        if ($tokens === []) {
            return [];
        }

        $counts = array_count_values(array_column($tokens, 'term'));
        $total = count($tokens);
        $vector = [];

        foreach ($counts as $term => $count) {
            $inverseDocumentFrequency = log(($documentCount + 1) / (($documentFrequencies[$term] ?? 0) + 1)) + 1;
            $vector[$term] = ($count / $total) * $inverseDocumentFrequency;
        }

        return $vector;
    }

    private function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        if ($vectorA === [] || $vectorB === []) {
            return 0.0;
        }

        $dotProduct = 0.0;
        foreach ($vectorA as $term => $weight) {
            $dotProduct += $weight * ($vectorB[$term] ?? 0.0);
        }

        $magnitudeA = sqrt(array_sum(array_map(fn ($value) => $value * $value, $vectorA)));
        $magnitudeB = sqrt(array_sum(array_map(fn ($value) => $value * $value, $vectorB)));

        return $magnitudeA > 0 && $magnitudeB > 0
            ? $dotProduct / ($magnitudeA * $magnitudeB)
            : 0.0;
    }

    /** @return array<string, array<int, int>> */
    private function shingles(array $tokens): array
    {
        $shingles = [];
        $terms = array_column($tokens, 'term');

        for ($index = 0; $index <= count($terms) - self::SHINGLE_SIZE; $index++) {
            $key = implode(' ', array_slice($terms, $index, self::SHINGLE_SIZE));
            $shingles[$key][] = $index;
        }

        return $shingles;
    }

    private function matchingPassages(
        string $submissionText,
        string $sourceText,
        array $submissionTokens,
        array $sourceTokens,
        array $sharedShingles,
        array $sourceShingles
    ): array {
        $passages = [];

        foreach ($sharedShingles as $shingle => $submissionPositions) {
            $submissionPosition = $submissionPositions[0];
            $sourcePosition = $sourceShingles[$shingle][0];
            $passages[] = [
                'phrase' => $shingle,
                'submission_excerpt' => $this->excerptAround($submissionText, $submissionTokens[$submissionPosition]['offset']),
                'source_excerpt' => $this->excerptAround($sourceText, $sourceTokens[$sourcePosition]['offset']),
            ];

            if (count($passages) >= 3) {
                break;
            }
        }

        return $passages;
    }

    private function excerptAroundSharedTerm(
        string $text,
        array $tokens,
        array $otherTokens
    ): string {
        $otherTerms = array_fill_keys(array_column($otherTokens, 'term'), true);

        foreach ($tokens as $token) {
            if (isset($otherTerms[$token['term']])) {
                return $this->excerptAround($text, $token['offset']);
            }
        }

        return $this->excerptAround($text, 0);
    }

    private function excerptAround(string $text, int $byteOffset, int $length = 240): string
    {
        $start = max(0, $byteOffset - 80);
        $excerpt = mb_strcut($text, $start, $length, 'UTF-8');
        $excerptEnd = $start + strlen($excerpt);
        $excerpt = trim(preg_replace('/\s+/u', ' ', $excerpt) ?? $excerpt);
        $prefix = $start > 0 ? "\u{2026}" : '';
        $suffix = $excerptEnd < strlen($text) ? "\u{2026}" : '';

        return $prefix.$excerpt.$suffix;
    }
}
