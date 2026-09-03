<?php

interface ParserInterface {
    /**
     * Parse raw 2D array from Google Sheet tab
     *
     * @param array $rows Raw 2D array of rows from Google Sheet
     * @param array $sheetRegistry Registry metadata (sheet_registry row)
     * @return array Array of normalized associative row arrays ready for DB upsert
     */
    public function parse(array $rows, array $sheetRegistry): array;
}
