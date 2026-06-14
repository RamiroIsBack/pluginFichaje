<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_ajax_pf_clock_in', 'pf_ajax_clock_in' );
function pf_ajax_clock_in() {
    check_ajax_referer( 'pf_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => __( 'No tienes sesión iniciada.', 'plugin-fichaje' ) ] );
    $uid = get_current_user_id();
    if ( ! current_user_can( 'manage_options' ) && get_user_meta( $uid, 'pf_empleado', true ) !== '1' ) {
        wp_send_json_error( [ 'message' => __( 'No tienes acceso al sistema de fichaje.', 'plugin-fichaje' ) ] );
    }
    $result = pf_clock_in( $uid );
    if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    $record = pf_get_record( $result );
    wp_send_json_success( [
        'message'      => __( '✅ Entrada registrada correctamente.', 'plugin-fichaje' ),
        'hora_entrada' => mysql2date( 'd/m/Y H:i:s', $record->hora_entrada ),
        'record_id'    => $result,
    ] );
}

add_action( 'wp_ajax_pf_clock_out', 'pf_ajax_clock_out' );
function pf_ajax_clock_out() {
    check_ajax_referer( 'pf_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => __( 'No tienes sesión iniciada.', 'plugin-fichaje' ) ] );
    $uid = get_current_user_id();
    if ( ! current_user_can( 'manage_options' ) && get_user_meta( $uid, 'pf_empleado', true ) !== '1' ) {
        wp_send_json_error( [ 'message' => __( 'No tienes acceso al sistema de fichaje.', 'plugin-fichaje' ) ] );
    }
    $result = pf_clock_out( $uid );
    if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    $record = pf_get_record( $result );
    wp_send_json_success( [
        'message'     => __( '✅ Salida registrada correctamente.', 'plugin-fichaje' ),
        'hora_salida' => mysql2date( 'd/m/Y H:i:s', $record->hora_salida ),
        'duracion'    => pf_format_duration( $record->hora_entrada, $record->hora_salida ),
        'record_id'   => $result,
    ] );
}

add_action( 'wp_ajax_pf_check_status', 'pf_ajax_check_status' );
function pf_ajax_check_status() {
    check_ajax_referer( 'pf_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();
    $status = pf_get_user_status( get_current_user_id() );
    $data = [ 'is_clocked_in' => $status['is_clocked_in'] ];
    if ( $status['record'] ) {
        $data['hora_entrada'] = mysql2date( 'd/m/Y H:i:s', $status['record']->hora_entrada );
        $data['record_id']    = $status['record']->id;
    }
    wp_send_json_success( $data );
}

add_action( 'wp_ajax_pf_admin_create', 'pf_ajax_admin_create' );
function pf_ajax_admin_create() {
    check_ajax_referer( 'pf_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => __( 'No tienes permisos.', 'plugin-fichaje' ) ] );
    $user_id      = intval( $_POST['user_id'] ?? 0 );
    $hora_entrada = sanitize_text_field( $_POST['hora_entrada'] ?? '' );
    $hora_salida  = sanitize_text_field( $_POST['hora_salida'] ?? '' );
    $notas        = sanitize_textarea_field( $_POST['notas'] ?? '' );
    $motivo       = sanitize_textarea_field( $_POST['motivo'] ?? '' );
    if ( ! $user_id || ! $hora_entrada ) wp_send_json_error( [ 'message' => __( 'Faltan campos obligatorios.', 'plugin-fichaje' ) ] );
    if ( ! $motivo ) wp_send_json_error( [ 'message' => __( 'El motivo de la corrección manual es obligatorio (requisito legal).', 'plugin-fichaje' ) ] );
    $result = pf_create_manual_record( $user_id, $hora_entrada, $hora_salida ?: null, $notas, $motivo, get_current_user_id() );
    if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    wp_send_json_success( [ 'message' => __( 'Registro manual creado correctamente.', 'plugin-fichaje' ), 'id' => $result ] );
}

add_action( 'wp_ajax_pf_admin_edit', 'pf_ajax_admin_edit' );
function pf_ajax_admin_edit() {
    check_ajax_referer( 'pf_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => __( 'No tienes permisos.', 'plugin-fichaje' ) ] );
    $record_id    = intval( $_POST['record_id'] ?? 0 );
    $hora_entrada = sanitize_text_field( $_POST['hora_entrada'] ?? '' );
    $hora_salida  = sanitize_text_field( $_POST['hora_salida'] ?? '' );
    $notas        = sanitize_textarea_field( $_POST['notas'] ?? '' );
    $motivo       = sanitize_textarea_field( $_POST['motivo'] ?? '' );
    if ( ! $record_id ) wp_send_json_error( [ 'message' => __( 'ID de registro no válido.', 'plugin-fichaje' ) ] );
    if ( ! $motivo ) wp_send_json_error( [ 'message' => __( 'El motivo de la corrección es obligatorio (requisito legal).', 'plugin-fichaje' ) ] );
    $new_data = array_filter( [ 'hora_entrada' => $hora_entrada, 'hora_salida' => $hora_salida, 'notas' => $notas ] );
    $result = pf_edit_record( $record_id, $new_data, $motivo, get_current_user_id() );
    if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    wp_send_json_success( [ 'message' => __( 'Registro actualizado. Cambio registrado en auditoría.', 'plugin-fichaje' ) ] );
}

add_action( 'wp_ajax_pf_admin_delete', 'pf_ajax_admin_delete' );
function pf_ajax_admin_delete() {
    check_ajax_referer( 'pf_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => __( 'No tienes permisos.', 'plugin-fichaje' ) ] );
    $record_id = intval( $_POST['record_id'] ?? 0 );
    $motivo    = sanitize_textarea_field( $_POST['motivo'] ?? '' );
    if ( ! $record_id ) wp_send_json_error( [ 'message' => __( 'ID de registro no válido.', 'plugin-fichaje' ) ] );
    if ( ! $motivo ) wp_send_json_error( [ 'message' => __( 'El motivo es obligatorio antes de eliminar un registro.', 'plugin-fichaje' ) ] );
    $result = pf_delete_record( $record_id, $motivo, get_current_user_id() );
    if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    wp_send_json_success( [ 'message' => __( 'Registro eliminado. Acción registrada en auditoría.', 'plugin-fichaje' ) ] );
}

add_action( 'wp_ajax_pf_admin_get_record', 'pf_ajax_admin_get_record' );
function pf_ajax_admin_get_record() {
    check_ajax_referer( 'pf_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
    $record_id = intval( $_POST['record_id'] ?? 0 );
    $record    = pf_get_record( $record_id );
    if ( ! $record ) wp_send_json_error( [ 'message' => __( 'Registro no encontrado.', 'plugin-fichaje' ) ] );
    wp_send_json_success( [
        'id'           => $record->id,
        'user_id'      => $record->user_id,
        'hora_entrada' => $record->hora_entrada,
        'hora_salida'  => $record->hora_salida,
        'notas'        => $record->notas,
        'tipo'         => $record->tipo,
    ] );
}
