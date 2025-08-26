<?php

/**
 * PDF Export functionality for wiki pages
 * Uses TCPDF library for PDF generation
 */

class PDFExporter
{
  private $wiki;
  private $cache;

  public function __construct($wiki, $cache = null)
  {
    $this->wiki = $wiki;
    $this->cache = $cache;

    // Load TCPDF
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
      require_once __DIR__ . '/../vendor/autoload.php';
    } elseif (file_exists(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php')) {
      require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
    } else {
      throw new Exception('TCPDF library not found. Install via: composer require tecnickcom/tcpdf');
    }
  }

  /**
   * Export a single page to PDF
   */
  public function exportPage($path, $options = [])
  {
    $content = $this->wiki->getPageContent($path);
    $title = $this->wiki->getPageTitle($path);

    if ($content === null) {
      throw new Exception('Page not found');
    }

    // Default options
    $options = array_merge([
      'format' => 'A4',
      'orientation' => 'P',
      'margins' => [15, 15, 15, 15],
      'include_header' => true,
      'include_footer' => true
    ], $options);

    $pdf = new TCPDF($options['orientation'], PDF_UNIT, $options['format'], true, 'UTF-8');

    // Set document information
    $pdf->SetCreator(WIKI_TITLE);
    $pdf->SetAuthor(WIKI_TITLE);
    $pdf->SetTitle($title);
    $pdf->SetSubject('Wiki Export');

    // Set margins
    $pdf->SetMargins($options['margins'][0], $options['margins'][1], $options['margins'][2]);
    $pdf->SetAutoPageBreak(true, $options['margins'][3]);

    // Set header and footer
    if ($options['include_header']) {
      $pdf->SetHeaderData('', 0, $title, WIKI_TITLE, [0, 64, 128], [0, 64, 128]);
    }

    if ($options['include_footer']) {
      $pdf->setFooterData([0, 64, 0], [0, 64, 128]);
    }

    // Add page
    $pdf->AddPage();

    // Convert HTML content for PDF
    $pdfContent = $this->preparePDFContent($content, $path);

    // Write content
    $pdf->writeHTML($pdfContent, true, false, true, false, '');

    // Generate filename
    $filename = $this->generatePDFFilename($path, $title);

    return [
      'pdf' => $pdf,
      'filename' => $filename,
      'content' => $pdf->Output('', 'S') // Return as string
    ];
  }

  /**
   * Export multiple pages to PDF
   */
  public function exportSection($basePath, $options = [])
  {
    $navigation = $this->wiki->getNavigation();
    $pages = $this->collectPagesInSection($navigation, $basePath);

    if (empty($pages)) {
      throw new Exception('No pages found in section');
    }

    $options = array_merge([
      'format' => 'A4',
      'orientation' => 'P',
      'margins' => [15, 15, 15, 15],
      'include_toc' => true,
      'section_breaks' => true
    ], $options);

    $pdf = new TCPDF($options['orientation'], PDF_UNIT, $options['format'], true, 'UTF-8');

    // Set document information
    $sectionTitle = $this->generateTitleFromPath($basePath) . ' Section';
    $pdf->SetCreator(WIKI_TITLE);
    $pdf->SetAuthor(WIKI_TITLE);
    $pdf->SetTitle($sectionTitle);
    $pdf->SetSubject('Wiki Section Export');

    $pdf->SetMargins($options['margins'][0], $options['margins'][1], $options['margins'][2]);
    $pdf->SetAutoPageBreak(true, $options['margins'][3]);

    // Set header
    $pdf->SetHeaderData('', 0, $sectionTitle, WIKI_TITLE, [0, 64, 128], [0, 64, 128]);
    $pdf->setFooterData([0, 64, 0], [0, 64, 128]);

    // Table of contents page
    if ($options['include_toc']) {
      $pdf->AddPage();
      $pdf->SetFont('helvetica', 'B', 16);
      $pdf->Cell(0, 10, 'Table of Contents', 0, 1, 'L');
      $pdf->Ln(5);

      $pdf->SetFont('helvetica', '', 12);
      foreach ($pages as $page) {
        $pdf->Cell(0, 8, $page['title'], 0, 1, 'L');
      }
    }

    // Add pages
    foreach ($pages as $page) {
      if ($options['section_breaks']) {
        $pdf->AddPage();
      }

      $content = $this->wiki->getPageContent($page['path']);
      if ($content) {
        $pdfContent = $this->preparePDFContent($content, $page['path']);
        $pdf->writeHTML($pdfContent, true, false, true, false, '');
      }
    }

    $filename = $this->generatePDFFilename($basePath, $sectionTitle);

    return [
      'pdf' => $pdf,
      'filename' => $filename,
      'content' => $pdf->Output('', 'S')
    ];
  }

  /**
   * Prepare HTML content for PDF output
   */
  private function preparePDFContent($htmlContent, $path)
  {
    // Remove navigation elements
    $content = preg_replace('/<nav[^>]*>.*?<\/nav>/s', '', $htmlContent);

    // Convert relative links to absolute or remove them
    $content = preg_replace('/href="(?!https?:\/\/)([^"]+)"/i', 'href="#"', $content);

    // Add some basic PDF styling
    $styles = '
        <style>
        h1 { color: #2563eb; font-size: 18pt; margin-bottom: 10pt; }
        h2 { color: #374151; font-size: 16pt; margin-top: 15pt; margin-bottom: 8pt; }
        h3 { color: #374151; font-size: 14pt; margin-top: 12pt; margin-bottom: 6pt; }
        p { font-size: 11pt; line-height: 1.4; margin-bottom: 8pt; }
        ul, ol { font-size: 11pt; margin-bottom: 8pt; }
        li { margin-bottom: 3pt; }
        code { background-color: #f3f4f6; padding: 2pt; font-family: monospace; }
        pre { background-color: #f3f4f6; padding: 8pt; margin: 8pt 0; }
        table { border-collapse: collapse; width: 100%; margin: 10pt 0; }
        th, td { border: 1pt solid #d1d5db; padding: 6pt; font-size: 10pt; }
        th { background-color: #f9fafb; font-weight: bold; }
        </style>';

    return $styles . $content;
  }

  /**
   * Generate PDF filename from path and title
   */
  private function generatePDFFilename($path, $title)
  {
    $filename = $title ?: 'wiki-export';
    $filename = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $filename);
    $filename = trim($filename, '-');
    $filename = strtolower($filename);

    return $filename . '-' . date('Y-m-d') . '.pdf';
  }

  /**
   * Collect all pages in a section recursively
   */
  private function collectPagesInSection($navigation, $basePath)
  {
    $pages = [];

    foreach ($navigation as $item) {
      if ($item['type'] === 'page' && strpos($item['path'], $basePath) === 0) {
        $pages[] = [
          'title' => $item['name'],
          'path' => $item['path']
        ];
      } elseif ($item['type'] === 'category' && !empty($item['children'])) {
        $childPages = $this->collectPagesInSection($item['children'], $basePath);
        $pages = array_merge($pages, $childPages);
      }
    }

    return $pages;
  }

  /**
   * Generate title from path
   */
  private function generateTitleFromPath($path)
  {
    $basename = basename($path);
    return ucwords(str_replace(['-', '_'], ' ', $basename));
  }
}
