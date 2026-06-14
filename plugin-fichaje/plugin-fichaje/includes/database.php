<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pf_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $reg = $wpdb->prefix . PF_TABLE_REG;
    $aud = $wpdb->prefix . PF_TABLE_AUD;

    $sql_reg = "CREATE TABLE IF NOT EXISTS {$reg} (
        id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id      BIGINT(20) UNSIGNED NOT NULL,
        fecha        DATE        NOT NULL,
        hora_entrada DATETIME    NOT NULL,
        hora_salida  DATETIME    DEFAULT NULL,
        ip_entrada   VARCHAR(45) NOT NULL DEFAULT '',
        ip_salida    VARCHAR(45) DEFAULT NULL,
        tipo         VARCHAR(20) NOT NULL DEFAULT 'normal',
        notas        TEXT        DEFAULT NULL,
        created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user_fecha (user_id, fecha),
        KEY idx_fecha (fecha)
    ) {$charset};";

    $sql_aud = "CREATE TABLE IF NOT EXISTS {$aud} (
        id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        registro_id    BIGINT(20) UNSIGNED NOT NULL,
        accion         VARCHAR(30)  NOT NULL,
        admin_user_id  BIGINT(20) UNSIGNED NOT NULL,
        campo_cambiado VARCHAR(50)  DEFAULT NULL,
        valor_anterior TEXT         DEFAULT NULL,
        valor_nuevo    TEXT         DEFAULT NULL,
        motivo         TEXT         NOT NULL,
        ip_admin       VARCHAR(45)  NOT NULL DEFAULT '',
        created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_registro (registro_id),
        KEY idx_admin (admin_user_id),
        KEY idx_created (created_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_reg );
    dbDelta( $sql_aud );
    update_option( 'pf_db_version', PF_VERSION );
}

function pf_get_user_ip() {
    if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) )        return sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )  return sanitize_text_field( explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0] );
    return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
}

function pf_clock_in( $user_id, $tipo = 'normal', $notas = '', $fecha_hora = '' ) {
    global $wpdb;
    $table = $wpdb->prefix . PF_TABLE_REG;
    $open  = pf_get_open_record( $user_id );
    if ( $open ) return new WP_Error( 'already_in', __( 'Ya tienes una jornada abierta sin fichar salida.', 'plugin-fichaje' ) );
    $now   = $fecha_hora ?: current_time( 'mysql' );
    $fecha = substr( $now, 0, 10 );
    $result = $wpdb->insert( $table, [
        'user_id'      => intval( $user_id ),
        'fecha'        => $fecha,
        'hora_entrada' => $now,
        'ip_entrada'   => pf_get_user_ip(),
        'tipo'         => sanitize_text_field( $tipo ),
        'notas'        => sanitize_textarea_field( $notas ),
    ], [ '%d', '%s', '%s', '%s', '%s', '%s' ] );
    if ( false === $result ) return new WP_Error( 'db_error', $wpdb->last_error );
    return $wpdb->insert_id;
}

function pf_clock_out( $user_id, $tipo = 'normal', $notas = '', $fecha_hora = '' ) {
    global $wpdb;
    $table = $wpdb->prefix . PF_TABLE_REG;
    $open  = pf_get_open_record( $user_id );
    if ( ! $open ) return new WP_Error( 'not_in', __( 'No hay jornada abierta para registrar la salida.', 'plugin-fichaje' ) );
    $now = $fecha_hora ?: current_time( 'mysql' );
    $result = $wpdb->update( $table, [
        'hora_salida' => $now,
        'ip_salida'   => pf_get_user_ip(),
        'tipo'        => sanitize_text_field( $tipo ),
        'notas'       => sanitize_textarea_field( $notas ) ?: $open->notas,
    ], [ 'id' => $open->id ], [ '%s', '%s', '%s', '%s' ], [ '%d' ] );
    if ( false === $result ) return new WP_Error( 'db_error', $wpdb->last_error );
    return $open->id;
}

function pf_get_open_record( $user_id ) {
    global $wpdb;
    $table = $wpdb->prefix . PF_TABLE_REG;
    return $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d AND hora_salida IS NULL ORDER BY hora_entrada DESC LIMIT 1",
        $user_id
    ) );
}

function pf_get_user_status( $user_id ) {
    $open = pf_get_open_record( $user_id );
    return [ 'is_clocked_in' => ! empty( $open ), 'record' => $open ];
}

function pf_get_records( $args = [] ) {
    global $wpdb;
    $table = $wpdb->prefix . PF_TABLE_REG;
    $defaults = [ 'user_id' => 0, 'fecha_from' => '', 'fecha_to' => '', 'limit' => 100, 'offset' => 0, 'orderby' => 'hora_entrada', 'order' => 'DESC' ];
    $args = wp_parse_args( $args, $defaults );
    $where = []; $prepare = [];
    if ( $args['user_id'] )    { $where[] = 'r.user_id = %d'; $prepare[] = intval( $args['user_id'] ); }
    if ( $args['fecha_from'] ) { $where[] = 'r.fecha >= %s'; $prepare[] = $args['fecha_from']; }
    if ( $args['fecha_to'] )   { $where[] = 'r.fecha <= %s'; $prepare[] = $args['fecha_to']; }
    $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
    $allowed_orderby = [ 'hora_entrada', 'hora_salida', 'fecha', 'user_id' ];
    $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'hora_entrada';
    $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
    $sql = "SELECT r.*, u.display_name, u.user_email FROM {$table} r LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID {$where_sql} ORDER BY r.{$orderby} {$order} LIMIT %d OFFSET %d";
    $prepare[] = intval( $args['limit'] );
    $prepare[] = intval( $args['offset'] );
    return $wpdb->get_results( $wpdb->prepare( $sql, ...$prepare ) );
}

function pf_count_records( $args = [] ) {
    global $wpdb;
    $table = $wpdb->prefix . PF_TABLE_REG;
    $defaults = [ 'user_id' => 0, 'fecha_from' => '', 'fecha_to' => '' ];
    $args = wp_parse_args( $args, $defaults );
    $where = []; $prepare = [];
    if ( $args['user_id'] )    { $where[] = 'user_id = %d'; $prepare[] = intval( $args['user_id'] ); }
    if ( $args['fecha_from'] ) { $where[] = 'fecha >= %s'; $prepare[] = $args['fecha_from']; }
    if ( $args['fecha_to'] )   { $where[] = 'fecha <= %s'; $prepare[] = $args['fecha_to']; }
    $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
    $sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
    return (int) ( $prepare ? $wpdb->get_var( $wpdb->prepare( $sql, ...$prepare ) ) : $wpdb->get_var( $sql ) );
}

function pf_get_record( $id ) {
    global $wpdb;
    $table = $wpdb->prefix . PF_TABLE_REG;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
}

function pf_edit_record( $record_id, $new_data, $motivo, $admin_id ) {
    global $wpdb;
    $table = $wpdb->prefix . PF_TABLE_REG;
    $aud   = $wpdb->prefix . PF_TABLE_AUD;
    $old   = pf_get_record( $record_id );
    if ( ! $old ) return new WP_Error( 'not_found', __( 'Registro no encontrado.', 'plugin-fichaje' ) );
    $allowed = [ 'hora_entrada', 'hora_salida', 'notas', 'fecha' ];
    $update_data = []; $update_format = [];
    foreach ( $new_data as $campo => $valor ) {
        if ( ! in_array( $campo, $allowed, true ) ) continue;
        $valor_viejo = $old->$campo ?? '';
        $valor_nuevo = sanitize_text_field( $valor );
        if ( (string) $valor_viejo === (string) $valor_nuevo ) continue;
        $update_data[ $campo ] = $valor_nuevo;
        $update_format[] = '%s';
        $wpdb->insert( $aud, [
            'registro_id' => $record_id, 'accion' => 'editar', 'admin_user_id' => $admin_id,
            'campo_cambiado' => $campo, 'valor_anterior' => $valor_viejo, 'valor_nuevo' => $valor_nuevo,
            'motivo' => sanitize_textarea_field( $motivo ), 'ip_admin' => pf_get_user_ip(),
        ], [ '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ] );
    }
    if ( empty( $update_data ) ) return true;
    $update_data['tipo'] = 'correccion'; $update_format[] = '%s';
    return false !== $wpdb->update( $table, $update_data, [ 'id' => $record_id ], $update_format, [ '%d' ] );
}

function pf_delete_record( $record_id, $motivo, $admin_id ) {
    global $wpdb;
    $table = $wpdb->prefix . PF_TABLE_REG;
    $aud   = $wpdb->prefix . PF_TABLE_AUD;
    $old   = pf_get_record( $record_id );
    if ( ! $old ) return new WP_Error( 'not_found', __( 'Registro no encontrado.', 'plugin-fichaje' ) );
    $wpdb->insert( $aud, [
        'registro_id' => $record_id, 'accion' => 'eliminar', 'admin_user_id' => $admin_id,
        'campo_cambiado' => 'registro_completo', 'valor_anterior' => json_encode( $old ), 'valor_nuevo' => null,
        'motivo' => sanitize_textarea_field( $motivo ), 'ip_admin' => pf_get_user_ip(),
    ], [ '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ] );
    return (bool) $wpdb->delete( $table, [ 'id' => $record_id ], [ '%d' ] );
}

function pf_create_manual_record( $user_id, $hora_entrada, $hora_salida, $notas, $motivo, $admin_id ) {
    global $wpdb;
    $table = $wpdb->prefix . PF_TABLE_REG;
    $aud   = $wpdb->prefix . PF_TABLE_AUD;
    $fecha = substr( $hora_entrada, 0, 10 );
    $result = $wpdb->insert( $table, [
        'user_id' => intval( $user_id ), 'fecha' => $fecha,
        'hora_entrada' => sanitize_text_field( $hora_entrada ),
        'hora_salida'  => $hora_salida ? sanitize_text_field( $hora_salida ) : null,
        'ip_entrada' => pf_get_user_ip(), 'ip_salida' => $hora_salida ? pf_get_user_ip() : null,
        'tipo' => 'manual', 'notas' => sanitize_textarea_field( $notas ),
    ], [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ] );
    if ( false === $result ) return new WP_Error( 'db_error', $wpdb->last_error );
    $new_id = $wpdb->insert_id;
    $wpdb->insert( $aud, [
        'registro_id' => $new_id, 'accion' => 'crear', 'admin_user_id' => $admin_id,
        'campo_cambiado' => 'registro_completo', 'valor_anterior' => null,
        'valor_nuevo' => json_encode( [ 'user_id' => $user_id, 'hora_entrada' => $hora_entrada, 'hora_salida' => $hora_salida ] ),
        'motivo' => sanitize_textarea_field( $motivo ), 'ip_admin' => pf_get_user_ip(),
    ], [ '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ] );
    return $new_id;
}

function pf_get_all_users() {
    return get_users( [
        'orderby'    => 'display_name',
        'order'      => 'ASC',
        'fields'     => [ 'ID', 'display_name', 'user_email' ],
        'meta_query' => [ [ 'key' => 'pf_empleado', 'value' => '1' ] ],
    ] );
}

function pf_format_duration( $entrada, $salida ) {
    if ( ! $entrada || ! $salida ) return '—';
    $diff = strtotime( $salida ) - strtotime( $entrada );
    if ( $diff <= 0 ) return '—';
    return sprintf( '%dh %02dmin', floor( $diff / 3600 ), floor( ( $diff % 3600 ) / 60 ) );
}
