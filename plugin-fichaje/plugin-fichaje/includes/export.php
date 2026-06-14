<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_init', 'pf_handle_export' );
function pf_handle_export() {
    if ( empty( $_GET['pf_export'] ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
    check_admin_referer( 'pf_export', 'pf_nonce' );
    $user_id = intval( $_GET['pf_user'] ?? 0 );
    $from    = sanitize_text_field( $_GET['pf_from'] ?? '' );
    $to      = sanitize_text_field( $_GET['pf_to']   ?? '' );
    $format  = sanitize_text_field( $_GET['pf_format'] ?? 'csv' );
    $records = pf_get_records( [ 'user_id' => $user_id, 'fecha_from' => $from, 'fecha_to' => $to, 'limit' => 100000, 'offset' => 0, 'orderby' => 'hora_entrada', 'order' => 'ASC' ] );
    $filename_base = 'fichaje_' . ( $from ?: 'all' ) . '_' . ( $to ?: 'all' );
    if ( $format === 'excel' ) {
        pf_export_excel( $records, $filename_base );
    } else {
        pf_export_csv( $records, $filename_base );
    }
    exit;
}

function pf_export_csv( $records, $filename ) {
    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );
    header( 'Pragma: no-cache' );
    echo "\xEF\xBB\xBF";
    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, [ 'ID', 'Trabajador', 'Email', 'Fecha', 'Hora Entrada', 'Hora Salida', 'Duración', 'Tipo', 'IP Entrada', 'IP Salida', 'Notas' ], ';' );
    foreach ( $records as $r ) {
        fputcsv( $out, [
            $r->id, $r->display_name, $r->user_email,
            mysql2date( 'd/m/Y', $r->hora_entrada ),
            mysql2date( 'H:i:s', $r->hora_entrada ),
            $r->hora_salida ? mysql2date( 'H:i:s', $r->hora_salida ) : '',
            pf_format_duration( $r->hora_entrada, $r->hora_salida ),
            $r->tipo, $r->ip_entrada, $r->ip_salida ?? '', $r->notas ?? '',
        ], ';' );
    }
    fclose( $out );
}

function pf_export_excel( $records, $filename ) {
    header( 'Content-Type: application/vnd.ms-excel; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '.xls"' );
    header( 'Pragma: no-cache' );
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    echo '<Styles><Style ss:ID="h"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1e3a5f" ss:Pattern="Solid"/></Style></Styles>' . "\n";
    echo '<Worksheet ss:Name="Fichajes"><Table>' . "\n";
    $cols = [ 'ID', 'Trabajador', 'Email', 'Fecha', 'Hora Entrada', 'Hora Salida', 'Duración', 'Tipo', 'IP Entrada', 'IP Salida', 'Notas' ];
    echo '<Row>';
    foreach ( $cols as $col ) echo '<Cell ss:StyleID="h"><Data ss:Type="String">' . htmlspecialchars( $col, ENT_XML1, 'UTF-8' ) . '</Data></Cell>';
    echo '</Row>' . "\n";
    foreach ( $records as $r ) {
        $cells = [
            [ $r->id, 'Number' ], [ $r->display_name, 'String' ], [ $r->user_email, 'String' ],
            [ mysql2date( 'd/m/Y', $r->hora_entrada ), 'String' ],
            [ mysql2date( 'H:i:s', $r->hora_entrada ), 'String' ],
            [ $r->hora_salida ? mysql2date( 'H:i:s', $r->hora_salida ) : '', 'String' ],
            [ pf_format_duration( $r->hora_entrada, $r->hora_salida ), 'String' ],
            [ $r->tipo, 'String' ], [ $r->ip_entrada, 'String' ],
            [ $r->ip_salida ?? '', 'String' ], [ $r->notas ?? '', 'String' ],
        ];
        echo '<Row>';
        foreach ( $cells as [ $val, $type ] ) echo '<Cell><Data ss:Type="' . $type . '">' . htmlspecialchars( (string)$val, ENT_XML1, 'UTF-8' ) . '</Data></Cell>';
        echo '</Row>' . "\n";
    }
    echo '</Table></Worksheet></Workbook>' . "\n";
}
