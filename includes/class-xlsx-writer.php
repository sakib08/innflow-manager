<?php
defined( 'ABSPATH' ) || exit;

/**
 * Minimal dependency-free XLSX (Office Open XML) writer.
 * Produces a single-sheet workbook readable by Excel, Google Sheets and LibreOffice.
 */
class InnflowManagerXlsx_Writer {

	private $rows = array();

	public static function is_available() {
		return class_exists( 'ZipArchive' );
	}

	public function add_row( array $cells ) {
		$this->rows[] = $cells;
	}

	public function output() {
		$tmp = wp_tempnam( 'ifmpp-export.xlsx' );
		$zip = new ZipArchive();
		$zip->open( $tmp, ZipArchive::OVERWRITE );

		$zip->addFromString( '[Content_Types].xml', $this->content_types_xml() );
		$zip->addFromString( '_rels/.rels', $this->package_rels_xml() );
		$zip->addFromString( 'xl/workbook.xml', $this->workbook_xml() );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', $this->workbook_rels_xml() );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $this->sheet_xml() );
		$zip->close();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local temp zip created by ZipArchive.
		$contents = file_get_contents( $tmp );
		wp_delete_file( $tmp );
		return $contents;
	}

	private function col_letter( $index ) {
		$letter = '';
		$index++;
		while ( $index > 0 ) {
			$mod    = ( $index - 1 ) % 26;
			$letter = chr( 65 + $mod ) . $letter;
			$index  = (int) ( ( $index - $mod ) / 26 );
		}
		return $letter;
	}

	private function esc( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	private function content_types_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '</Types>';
	}

	private function package_rels_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';
	}

	private function workbook_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="Room Types" sheetId="1" r:id="rId1"/></sheets>'
			. '</workbook>';
	}

	private function workbook_rels_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '</Relationships>';
	}

	private function sheet_xml() {
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

		foreach ( $this->rows as $r_index => $cells ) {
			$row_num = $r_index + 1;
			$xml    .= '<row r="' . $row_num . '">';
			foreach ( $cells as $c_index => $value ) {
				$ref = $this->col_letter( $c_index ) . $row_num;
				if ( is_numeric( $value ) && '' !== trim( (string) $value ) ) {
					$xml .= '<c r="' . $ref . '"><v>' . $this->esc( $value ) . '</v></c>';
				} else {
					$xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $this->esc( $value ) . '</t></is></c>';
				}
			}
			$xml .= '</row>';
		}

		$xml .= '</sheetData></worksheet>';
		return $xml;
	}
}
