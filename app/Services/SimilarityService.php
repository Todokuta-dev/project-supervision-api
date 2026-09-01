<?php

namespace App\Services;

use App\Models\RepositoryItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory;

class SimilarityService
{
    /**
     * Extract plain text from a stored file, given its storage path
     * (relative path as saved via Storage::disk('public')->put(...)).
     */
    public function extractText(string $storagePath): string
    {
        $fullPath = Storage::disk('public')->path($storagePath);
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        try {
            if ($extension === 'pdf') {
                $parser = new PdfParser();
                $pdf = $parser->parseFile($fullPath);
                return $pdf->getText();
            }

            if (in_array($extension, ['doc', 'docx'])) {
                $phpWord = IOFactory::load($fullPath);
                $text = '';
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if (method_exists($element, 'getText')) {
                            $text .= $element->getText() . ' ';
                        } elseif (method_exists($element, 'getElements')) {
                            foreach ($element->getElements() as $childElement) {
                                if (method_exists($childElement, 'getText')) {
                                    $text .= $childElement->getText() . ' ';
                                }
                            }
                        }
                    }
                }
                return $text;
            }
        } catch (\Throwable $e) {
            Log::warning("SimilarityService: failed to extract text from {$storagePath}: " . $e->getMessage());
            return '';
        }

        return '';
    }

    /**
     * Tokenize text into lowercase words, stripped of punctuation,
     * ignoring very short tokens (noise reduction).
     */
    private function tokenize(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', trim($text));
        return array_values(array_filter($words, fn ($w) => strlen($w) > 2));
    }

    /**
     * Build a TF (term frequency) map for one document's tokens.
     */
    private function termFrequency(array $tokens): array
    {
        $tf = [];
        $total = count($tokens);
        if ($total === 0) {
            return [];
        }
        foreach ($tokens as $token) {
            $tf[$token] = ($tf[$token] ?? 0) + 1;
        }
        foreach ($tf as $term => $count) {
            $tf[$term] = $count / $total;
        }
        return $tf;
    }

    /**
     * Compute cosine similarity between two TF maps, using the union
     * of their terms as the vector space.
     */
    private function cosineSimilarity(array $tfA, array $tfB): float
    {
        $terms = array_unique(array_merge(array_keys($tfA), array_keys($tfB)));

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        foreach ($terms as $term) {
            $a = $tfA[$term] ?? 0.0;
            $b = $tfB[$term] ?? 0.0;
            $dotProduct += $a * $b;
            $magnitudeA += $a * $a;
            $magnitudeB += $b * $b;
        }

        if ($magnitudeA === 0.0 || $magnitudeB === 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($magnitudeA) * sqrt($magnitudeB));
    }

    /**
     * Compare a submission's extracted text against every RepositoryItem
     * that has full_text populated. Returns an overall score (0-100) and
     * a matched_sources array, matching the SimilarityReport contract.
     */
    public function compareAgainstRepository(string $submissionText): array
    {
        $submissionTokens = $this->tokenize($submissionText);
        $submissionTf = $this->termFrequency($submissionTokens);

        if (empty($submissionTf)) {
            return ['score' => 0, 'matched_sources' => []];
        }

        $items = RepositoryItem::whereNotNull('full_text')
            ->where('full_text', '!=', '')
            ->get();

        $matches = [];

        foreach ($items as $item) {
            $sourceTokens = $this->tokenize($item->full_text);
            $sourceTf = $this->termFrequency($sourceTokens);

            $similarity = $this->cosineSimilarity($submissionTf, $sourceTf);
            $percentage = round($similarity * 100, 2);

            // Only report sources with a meaningful similarity — avoids
            // cluttering results with noise from unrelated documents.
            if ($percentage >= 5) {
                $matches[] = [
                    'source_title' => $item->title,
                    'source_id' => $item->repo_id,
                    'matched_percentage' => $percentage,
                    'matched_excerpt' => $this->excerpt($submissionText, 200),
                    'source_excerpt' => $this->excerpt($item->full_text, 200),
                ];
            }
        }

        // Sort by highest match first, keep top 5 to avoid an overwhelming report.
        usort($matches, fn ($a, $b) => $b['matched_percentage'] <=> $a['matched_percentage']);
        $matches = array_slice($matches, 0, 5);

        $overallScore = empty($matches) ? 0 : $matches[0]['matched_percentage'];

        return [
            'score' => $overallScore,
            'matched_sources' => $matches,
        ];
    }

    /**
     * Grab a short excerpt from the start of a text block, for display.
     */
    private function excerpt(string $text, int $length): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text));
        return mb_substr($clean, 0, $length) . (mb_strlen($clean) > $length ? '...' : '');
    }
}