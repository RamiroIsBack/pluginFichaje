<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', 'pf_register_rest_routes' );
function pf_register_rest_routes() {
    $ns   = 'fichaje/v1';
    $read = [ 'methods' => WP_REST_Server::READABLE, 'permission_callback' => 'pf_rest_permission' ];

    register_rest_route( $ns, '/resumen-semanal', $read + [
        'callback' => 'pf_rest_resumen_semanal',
        'args'     => [
            'week' => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => date( 'Y-\WW' ) ],
        ],
    ] );

    register_rest_route( $ns, '/empleados',   $read + [ 'callback' => 'pf_rest_empleados' ] );
    register_rest_route( $ns, '/incidencias', $read + [ 'callback' => 'pf_rest_incidencias' ] );

    register_rest_route( $ns, '/registros/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'pf_rest_edit_registro',
        'permission_callback' => 'pf_rest_permission',
        'args' => [
            'id'           => [ 'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0 ],
            'hora_entrada' => [ 'sanitize_callback' => 'sanitize_text_field' ],
            'hora_salida'  => [ 'sanitize_callback' => 'sanitize_text_field' ],
            'notas'        => [ 'sanitize_callback' => 'sanitize_textarea_field' ],
            'motivo'       => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'validate_callback' => fn( $v ) => ! empty( trim( $v ) ),
            ],
        ],
    ] );
}

function pf_rest_permission(): bool {
    if ( ! is_user_logged_in() ) return false;
    $uid = get_current_user_id();
    return current_user_can( 'manage_options' ) || get_user_meta( $uid, 'pf_control', true ) === '1';
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function pf_week_bounds( string $param ): array {
    if ( preg_match( '/^(\d{4})-W?(\d{1,2})$/', $param, $m ) ) {
        $dt = new DateTime();
        $dt->setISODate( (int) $m[1], (int) $m[2], 1 );
        $ini = $dt->format( 'Y-m-d' );
        $dt->modify( '+6 days' );
        return [ (int) $m[1], (int) $m[2], $ini, $dt->format( 'Y-m-d' ) ];
    }
    $dt  = new DateTime();
    $y   = (int) $dt->format( 'o' );
    $w   = (int) $dt->format( 'W' );
    $dt->setISODate( $y, $w, 1 );
    $ini = $dt->format( 'Y-m-d' );
    $dt->modify( '+6 days' );
    return [ $y, $w, $ini, $dt->format( 'Y-m-d' ) ];
}

function pf_horas_entre( string $entrada, ?string $salida ): ?float {
    if ( ! $salida ) return null;
    return round( ( strtotime( $salida ) - strtotime( $entrada ) ) / 3600, 2 );
}

function pf_normalizar_datetime( string $valor ): string {
    // Acepta "2025-06-14T09:00" (datetime-local) o "2025-06-14 09:00:00" (MySQL)
    $valor = str_replace( 'T', ' ', $valor );
    if ( strlen( $valor ) === 16 ) $valor .= ':00';
    return $valor;
}

function pf_incidencia_row( object $r, string $tipo ): array {
    return [
        'tipo'              => $tipo,
        'registro_id'       => (int) $r->id,
        'trabajador_id'     => (int) $r->user_id,
        'trabajador_nombre' => $r->display_name,
        'trabajador_email'  => $r->user_email,
        'hora_entrada'      => $r->hora_entrada,
        'fecha'             => substr( $r->hora_entrada, 0, 10 ),
    ];
}

// ─── GET /resumen-semanal ────────────────────────────────────────────────────

function pf_rest_resumen_semanal( WP_REST_Request $req ): WP_REST_Response {
    global $wpdb;
    $table = $wpdb->prefix . PF_TABLE_REG;

    [ $year, $week, $fecha_ini, $fecha_fin ] = pf_week_bounds( $req->get_param( 'week' ) );

    $hora_turno = get_option( 'pf_hora_inicio_turno', '' );
    $hoy        = date( 'Y-m-d' );

    $empleados = get_users( [
        'meta_key'   => 'pf_empleado',
        'meta_value' => '1',
        'orderby'    => 'display_name',
        'order'      => 'ASC',
        'fields'     => [ 'ID', 'display_name', 'user_email' ],
    ] );

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT r.id, r.user_id, r.hora_entrada, r.hora_salida, r.tipo, r.notas
         FROM {$table} r
         WHERE DATE(r.hora_entrada) BETWEEN %s AND %s
         ORDER BY r.user_id, r.hora_entrada ASC",
        $fecha_ini, $fecha_fin
    ) );

    // Agrupar por user_id → fecha
    $by_user_date = [];
    foreach ( $rows as $r ) {
        $by_user_date[ $r->user_id ][ substr( $r->hora_entrada, 0, 10 ) ][] = $r;
    }

    // Slots lunes→domingo
    $dias_es = [ 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo' ];
    $slots   = [];
    $dt      = new DateTime( $fecha_ini );
    for ( $i = 0; $i < 7; $i++ ) {
        $slots[] = [ 'fecha' => $dt->format( 'Y-m-d' ), 'dia' => $dias_es[ $i ], 'laborable' => $i < 5 ];
        $dt->modify( '+1 day' );
    }

    $trabajadores = [];

    foreach ( $empleados as $emp ) {
        $uid         = $emp->ID;
        $total_horas = 0.0;
        $inc_worker  = [];
        $dias_data   = [];

        foreach ( $slots as $slot ) {
            $fecha     = $slot['fecha'];
            $recs      = $by_user_date[ $uid ][ $fecha ] ?? [];
            $inc_dia   = [];
            $horas_dia = 0.0;
            $recs_data = [];

            foreach ( $recs as $r ) {
                $inc_reg = [];
                $horas   = pf_horas_entre( $r->hora_entrada, $r->hora_salida );

                // Llegada tarde
                if ( $hora_turno && substr( $r->hora_entrada, 11, 5 ) > $hora_turno ) {
                    $inc_reg[] = 'tarde';
                    $inc_dia[] = 'tarde';
                }

                // Olvidado fichar / jornada abierta
                if ( $r->hora_salida === null ) {
                    $t         = $fecha < $hoy ? 'olvidado_fichar' : 'jornada_abierta';
                    $inc_reg[] = $t;
                    $inc_dia[] = $t;
                }

                if ( $horas !== null ) {
                    $horas_dia   += $horas;
                    $total_horas += $horas;
                }

                $recs_data[] = [
                    'id'          => (int) $r->id,
                    'entrada'     => substr( $r->hora_entrada, 11, 8 ),
                    'salida'      => $r->hora_salida ? substr( $r->hora_salida, 11, 8 ) : null,
                    'horas'       => $horas,
                    'tipo'        => $r->tipo,
                    'incidencias' => array_values( array_unique( $inc_reg ) ),
                ];
            }

            // Sin registro en día laborable pasado
            if ( empty( $recs ) && $slot['laborable'] && $fecha < $hoy ) {
                $inc_dia[] = 'sin_registro';
            }

            $inc_dia   = array_values( array_unique( $inc_dia ) );
            $inc_worker = array_values( array_unique( array_merge( $inc_worker, $inc_dia ) ) );

            $dias_data[] = [
                'fecha'       => $fecha,
                'dia'         => $slot['dia'],
                'laborable'   => $slot['laborable'],
                'registros'   => $recs_data,
                'total_horas' => round( $horas_dia, 2 ),
                'incidencias' => $inc_dia,
            ];
        }

        $trabajadores[] = [
            'id'                 => (int) $uid,
            'nombre'             => $emp->display_name,
            'email'              => $emp->user_email,
            'total_horas_semana' => round( $total_horas, 2 ),
            'dias'               => $dias_data,
            'incidencias'        => $inc_worker,
        ];
    }

    return new WP_REST_Response( [
        'semana'       => sprintf( '%04d-W%02d', $year, $week ),
        'fecha_inicio' => $fecha_ini,
        'fecha_fin'    => $fecha_fin,
        'trabajadores' => $trabajadores,
    ], 200 );
}

// ─── GET /empleados ──────────────────────────────────────────────────────────

function pf_rest_empleados( WP_REST_Request $req ): WP_REST_Response {
    $empleados = get_users( [
        'meta_key'   => 'pf_empleado',
        'meta_value' => '1',
        'orderby'    => 'display_name',
        'order'      => 'ASC',
        'fields'     => [ 'ID', 'display_name', 'user_email' ],
    ] );
    return new WP_REST_Response(
        array_map( fn( $u ) => [ 'id' => (int) $u->ID, 'nombre' => $u->display_name, 'email' => $u->user_email ], $empleados ),
        200
    );
}

// ─── GET /incidencias ────────────────────────────────────────────────────────

function pf_rest_incidencias( WP_REST_Request $req ): WP_REST_Response {
    global $wpdb;
    $table      = $wpdb->prefix . PF_TABLE_REG;
    $hoy        = date( 'Y-m-d' );
    $hora_turno = get_option( 'pf_hora_inicio_turno', '' );

    $olvidados = $wpdb->get_results( $wpdb->prepare(
        "SELECT r.id, r.user_id, r.hora_entrada, u.display_name, u.user_email
         FROM {$table} r
         JOIN {$wpdb->users} u ON r.user_id = u.ID
         WHERE r.hora_salida IS NULL AND DATE(r.hora_entrada) < %s
         ORDER BY r.hora_entrada DESC",
        $hoy
    ) );

    $abiertas = $wpdb->get_results( $wpdb->prepare(
        "SELECT r.id, r.user_id, r.hora_entrada, u.display_name, u.user_email
         FROM {$table} r
         JOIN {$wpdb->users} u ON r.user_id = u.ID
         WHERE r.hora_salida IS NULL AND DATE(r.hora_entrada) = %s
         ORDER BY r.hora_entrada ASC",
        $hoy
    ) );

    $tardes = [];
    if ( $hora_turno ) {
        $hoy_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.id, r.user_id, r.hora_entrada, u.display_name, u.user_email
             FROM {$table} r
             JOIN {$wpdb->users} u ON r.user_id = u.ID
             WHERE DATE(r.hora_entrada) = %s
             ORDER BY r.hora_entrada ASC",
            $hoy
        ) );
        foreach ( $hoy_rows as $r ) {
            if ( substr( $r->hora_entrada, 11, 5 ) > $hora_turno ) {
                $tardes[] = pf_incidencia_row( $r, 'tarde' );
            }
        }
    }

    return new WP_REST_Response( [
        'olvidados_fichar'  => array_map( fn( $r ) => pf_incidencia_row( $r, 'olvidado_fichar' ), $olvidados ),
        'jornadas_abiertas' => array_map( fn( $r ) => pf_incidencia_row( $r, 'jornada_abierta' ), $abiertas ),
        'llegadas_tarde'    => $tardes,
        'total_alertas'     => count( $olvidados ) + count( $tardes ),
    ], 200 );
}

// ─── PATCH /registros/{id} ───────────────────────────────────────────────────

function pf_rest_edit_registro( WP_REST_Request $req ): WP_REST_Response {
    $record_id = (int) $req->get_param( 'id' );
    $motivo    = $req->get_param( 'motivo' );

    $record = pf_get_record( $record_id );
    if ( ! $record ) {
        return new WP_REST_Response( [ 'message' => __( 'Registro no encontrado.', 'plugin-fichaje' ) ], 404 );
    }

    $new_data = [];
    foreach ( [ 'hora_entrada', 'hora_salida', 'notas' ] as $campo ) {
        $val = $req->get_param( $campo );
        if ( $val !== null && $val !== '' ) {
            $new_data[ $campo ] = in_array( $campo, [ 'hora_entrada', 'hora_salida' ], true )
                ? pf_normalizar_datetime( $val )
                : $val;
        }
    }

    if ( empty( $new_data ) ) {
        return new WP_REST_Response( [ 'message' => __( 'No se han enviado campos a modificar.', 'plugin-fichaje' ) ], 400 );
    }

    $result = pf_edit_record( $record_id, $new_data, $motivo, get_current_user_id() );

    if ( is_wp_error( $result ) ) {
        return new WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
    }

    $updated = pf_get_record( $record_id );
    return new WP_REST_Response( [
        'message'      => __( 'Registro actualizado. Cambio registrado en auditoría.', 'plugin-fichaje' ),
        'id'           => (int) $updated->id,
        'hora_entrada' => $updated->hora_entrada,
        'hora_salida'  => $updated->hora_salida,
        'notas'        => $updated->notas,
        'tipo'         => $updated->tipo,
    ], 200 );
}
