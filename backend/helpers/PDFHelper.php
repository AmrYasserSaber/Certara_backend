<?php

declare(strict_types=1);

namespace App\Helpers;

if (!class_exists('FPDF', false)) {
    require_once __DIR__ . '/../vendor/setasign/fpdf/fpdf.php';
}

use FPDF;

/**
 * Certificate PDF generator.
 *
 * The helper produces a simple, branded PDF document for approved research
 * certificates and stores it in backend/uploads/certificates/.
 */
final class PDFHelper
{
    private const OUTPUT_DIR = __DIR__ . '/../uploads/certificates';

    /**
     * Generate a certificate PDF and return the relative file path.
     *
     * Expected keys:
     * - research_title
     * - student_name
     * - serial_number
     * - issue_date
     * - certificate_number
     * - manager_name (optional)
     * - research_id (optional)
     */
    public static function generateCertificate(array $data): string
    {
        self::ensureOutputDirectory();

        $certificateNumber = self::sanitizeFilePart((string) ($data['certificate_number'] ?? 'certificate'));
        $researchId = self::sanitizeFilePart((string) ($data['research_id'] ?? '0'));
        $timestamp = date('Ymd_His');
        $fileName = sprintf('certificate_%s_%s_%s.pdf', $certificateNumber, $researchId, $timestamp);
        $absolutePath = self::OUTPUT_DIR . '/' . $fileName;
        $relativePath = 'uploads/certificates/' . $fileName;

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();
        $pdf->SetMargins(18, 18, 18);

        // Border frame.
        $pdf->SetDrawColor(15, 76, 129);
        $pdf->SetLineWidth(1.2);
        $pdf->Rect(10, 10, 190, 277);

        // Header logo placeholder.
        $pdf->SetDrawColor(100, 116, 139);
        $pdf->SetLineWidth(0.4);
        $pdf->Rect(18, 18, 28, 28);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->SetXY(18, 29);
        $pdf->Cell(28, 5, self::pdfText('IRB LOGO'), 0, 0, 'C');

        // Title block.
        $pdf->SetXY(54, 18);
        $pdf->SetFont('Arial', 'B', 20);
        $pdf->SetTextColor(15, 76, 129);
        $pdf->Cell(0, 10, self::pdfText('Institutional Review Board Certificate'), 0, 1, 'L');

        $pdf->SetX(54);
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->MultiCell(0, 6, self::pdfText('This document confirms final approval of the submitted research.'), 0, 'L');

        $pdf->Ln(8);

        // Main content.
        $pdf->SetFont('Arial', '', 13);
        $pdf->SetTextColor(31, 41, 55);
        $pdf->MultiCell(0, 9, self::pdfText('Certificate Information'), 0, 'L');
        $pdf->Ln(2);

        $rows = [
            'Research Title' => (string) ($data['research_title'] ?? ''),
            'Student Name' => (string) ($data['student_name'] ?? ''),
            'Serial Number' => (string) ($data['serial_number'] ?? ''),
            'Certificate Number' => (string) ($data['certificate_number'] ?? ''),
            'Issue Date' => (string) ($data['issue_date'] ?? date('Y-m-d')),
        ];

        foreach ($rows as $label => $value) {
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(48, 8, self::pdfText($label . ':'), 0, 0, 'L');
            $pdf->SetFont('Arial', '', 11);
            $pdf->MultiCell(0, 8, self::pdfText($value !== '' ? $value : '-'), 0, 'L');
        }

        $pdf->Ln(8);

        // Statement block.
        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetFont('Arial', '', 11);
        $pdf->MultiCell(
            0,
            8,
            self::pdfText('The above certificate has been issued by the IRB manager and is valid as an official approval record.'),
            1,
            'L',
            true
        );

        $pdf->Ln(12);

        // Signature line.
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(110, 8, self::pdfText('Manager Signature'), 0, 0, 'L');
        $pdf->Ln(14);
        $pdf->SetX(95);
        $pdf->Cell(85, 0, '', 'T', 0, 'L');
        $pdf->Ln(4);
        $pdf->SetX(95);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(85, 6, self::pdfText((string) ($data['manager_name'] ?? 'IRB Manager')), 0, 0, 'C');

        $pdf->Output('F', $absolutePath);

        return $relativePath;
    }

    /**
     * Ensure the storage directory exists.
     */
    private static function ensureOutputDirectory(): void
    {
        if (!is_dir(self::OUTPUT_DIR)) {
            @mkdir(self::OUTPUT_DIR, 0775, true);
        }
    }

    /**
     * Convert a UTF-8 string into a PDF-safe string for the default FPDF fonts.
     */
    private static function pdfText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value);
        if ($converted === false || $converted === '') {
            return preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;
        }

        return $converted;
    }

    /**
     * Remove characters that are unsafe for file names.
     */
    private static function sanitizeFilePart(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', $value) ?? 'certificate';
        $value = trim($value, '._-');

        return $value !== '' ? $value : 'certificate';
    }
}
