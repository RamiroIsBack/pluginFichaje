<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', 'pf_admin_menu' );
function pf_admin_menu() {
    add_menu_page( __( 'Fichaje Laboral', 'plugin-fichaje' ), __( 'Fichaje', 'plugin-fichaje' ), 'manage_options', 'fichaje', 'pf_admin_page_render', 'dashicons-clock', 30 );
    add_submenu_page( 'fichaje', __( 'Registros', 'plugin-fichaje' ), __( 'Registros', 'plugin-fichaje' ), 'manage_options', 'fichaje', 'pf_admin_page_render' );
    add_submenu_page( 'fichaje', __( 'Empleados', 'plugin-fichaje' ), __( 'Empleados', 'plugin-fichaje' ), 'manage_options', 'fichaje-empleados', 'pf_admin_employees_render' );
    add_submenu_page( 'fichaje', __( 'Auditoría', 'plugin-fichaje' ), __( 'Auditoría', 'plugin-fichaje' ), 'manage_options', 'fichaje-auditoria', 'pf_admin_audit_render' );
    add_submenu_page( 'fichaje', __( 'Control', 'plugin-fichaje' ), __( 'Control', 'plugin-fichaje' ), 'manage_options', 'fichaje-control', 'pf_admin_control_render' );
}

function pf_tipo_label( $tipo ) {
    $labels = [ 'normal' => 'Normal', 'manual' => 'Manual', 'correccion' => 'Corrección' ];
    return $labels[ $tipo ] ?? $tipo;
}

function pf_export_url( $user_id, $from, $to, $format ) {
    return add_query_arg( [ 'pf_export' => 1, 'pf_user' => $user_id, 'pf_from' => $from, 'pf_to' => $to, 'pf_format' => $format, 'pf_nonce' => wp_create_nonce( 'pf_export' ) ], admin_url( 'admin.php' ) );
}

function pf_admin_page_render() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( __( 'No tienes permisos.', 'plugin-fichaje' ) );
    $filter_user  = intval( $_GET['pf_user'] ?? 0 );
    $filter_from  = sanitize_text_field( $_GET['pf_from'] ?? date( 'Y-m-01' ) );
    $filter_to    = sanitize_text_field( $_GET['pf_to']   ?? date( 'Y-m-d' ) );
    $current_page = max( 1, intval( $_GET['pf_page'] ?? 1 ) );
    $per_page     = 25;
    $offset       = ( $current_page - 1 ) * $per_page;
    $query_args   = [ 'user_id' => $filter_user, 'fecha_from' => $filter_from, 'fecha_to' => $filter_to, 'limit' => $per_page, 'offset' => $offset ];
    $records      = pf_get_records( $query_args );
    $total        = pf_count_records( $query_args );
    $total_pages  = ceil( $total / $per_page );
    $all_users    = pf_get_all_users();
    $active_today = pf_get_records( [ 'fecha_from' => date( 'Y-m-d' ), 'fecha_to' => date( 'Y-m-d' ), 'limit' => 200 ] );
    ?>
    <div class="wrap pf-admin-wrap">
        <h1><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Registro de Jornada Laboral', 'plugin-fichaje' ); ?></h1>
        <p class="pf-legal-notice"><?php esc_html_e( 'Sistema conforme al Real Decreto-ley 8/2019 (Art. 34.9 ET). Registros conservados 4 años. Correcciones manuales auditadas.', 'plugin-fichaje' ); ?></p>

        <div class="pf-card">
            <h2><?php esc_html_e( 'Estado hoy', 'plugin-fichaje' ); ?> — <?php echo esc_html( date_i18n( 'd/m/Y' ) ); ?></h2>
            <?php if ( empty( $active_today ) ) : ?>
            <p><?php esc_html_e( 'Nadie ha fichado hoy todavía.', 'plugin-fichaje' ); ?></p>
            <?php else : ?>
            <table class="pf-table widefat striped">
                <thead><tr><th><?php esc_html_e( 'Trabajador', 'plugin-fichaje' ); ?></th><th><?php esc_html_e( 'Entrada', 'plugin-fichaje' ); ?></th><th><?php esc_html_e( 'Salida', 'plugin-fichaje' ); ?></th><th><?php esc_html_e( 'Duración', 'plugin-fichaje' ); ?></th><th><?php esc_html_e( 'Estado', 'plugin-fichaje' ); ?></th></tr></thead>
                <tbody>
                <?php foreach ( $active_today as $r ) : ?>
                <tr>
                    <td><?php echo esc_html( $r->display_name ); ?></td>
                    <td><?php echo esc_html( mysql2date( 'H:i:s', $r->hora_entrada ) ); ?></td>
                    <td><?php echo $r->hora_salida ? esc_html( mysql2date( 'H:i:s', $r->hora_salida ) ) : '—'; ?></td>
                    <td><?php echo esc_html( pf_format_duration( $r->hora_entrada, $r->hora_salida ) ); ?></td>
                    <td><?php echo $r->hora_salida ? '<span class="pf-badge pf-badge-done">✅ Finalizada</span>' : '<span class="pf-badge pf-badge-active">🟢 En jornada</span>'; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="pf-card">
            <h2><?php esc_html_e( 'Historial de registros', 'plugin-fichaje' ); ?></h2>
            <form method="get" class="pf-filters">
                <input type="hidden" name="page" value="fichaje">
                <label><?php esc_html_e( 'Trabajador:', 'plugin-fichaje' ); ?>
                    <select name="pf_user">
                        <option value="0"><?php esc_html_e( 'Todos', 'plugin-fichaje' ); ?></option>
                        <?php foreach ( $all_users as $u ) : ?>
                        <option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $filter_user, $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?php esc_html_e( 'Desde:', 'plugin-fichaje' ); ?><input type="date" name="pf_from" value="<?php echo esc_attr( $filter_from ); ?>"></label>
                <label><?php esc_html_e( 'Hasta:', 'plugin-fichaje' ); ?><input type="date" name="pf_to" value="<?php echo esc_attr( $filter_to ); ?>"></label>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Filtrar', 'plugin-fichaje' ); ?></button>
                <a href="<?php echo esc_url( pf_export_url( $filter_user, $filter_from, $filter_to, 'csv' ) ); ?>" class="button button-secondary">⬇ <?php esc_html_e( 'Exportar CSV', 'plugin-fichaje' ); ?></a>
                <a href="<?php echo esc_url( pf_export_url( $filter_user, $filter_from, $filter_to, 'excel' ) ); ?>" class="button button-secondary">⬇ <?php esc_html_e( 'Exportar Excel', 'plugin-fichaje' ); ?></a>
            </form>

            <?php if ( empty( $records ) ) : ?>
            <p><?php esc_html_e( 'No hay registros para los filtros seleccionados.', 'plugin-fichaje' ); ?></p>
            <?php else : ?>
            <p class="pf-count"><?php printf( __( 'Total: %d registros', 'plugin-fichaje' ), $total ); ?></p>
            <table class="pf-table widefat striped">
                <thead><tr>
                    <th>#</th><th><?php esc_html_e( 'Trabajador', 'plugin-fichaje' ); ?></th>
                    <th><?php esc_html_e( 'Fecha', 'plugin-fichaje' ); ?></th>
                    <th><?php esc_html_e( 'Entrada', 'plugin-fichaje' ); ?></th>
                    <th><?php esc_html_e( 'Salida', 'plugin-fichaje' ); ?></th>
                    <th><?php esc_html_e( 'Duración', 'plugin-fichaje' ); ?></th>
                    <th><?php esc_html_e( 'Tipo', 'plugin-fichaje' ); ?></th>
                    <th><?php esc_html_e( 'Notas', 'plugin-fichaje' ); ?></th>
                    <th><?php esc_html_e( 'Acciones', 'plugin-fichaje' ); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ( $records as $r ) : ?>
                <tr data-id="<?php echo esc_attr( $r->id ); ?>">
                    <td><?php echo esc_html( $r->id ); ?></td>
                    <td><?php echo esc_html( $r->display_name ); ?><br><small><?php echo esc_html( $r->user_email ); ?></small></td>
                    <td><?php echo esc_html( mysql2date( 'd/m/Y', $r->hora_entrada ) ); ?></td>
                    <td><?php echo esc_html( mysql2date( 'H:i:s', $r->hora_entrada ) ); ?></td>
                    <td><?php echo $r->hora_salida ? esc_html( mysql2date( 'H:i:s', $r->hora_salida ) ) : '<span class="pf-open">⏳ Abierta</span>'; ?></td>
                    <td><?php echo esc_html( pf_format_duration( $r->hora_entrada, $r->hora_salida ) ); ?></td>
                    <td><span class="pf-tipo pf-tipo-<?php echo esc_attr( $r->tipo ); ?>"><?php echo esc_html( pf_tipo_label( $r->tipo ) ); ?></span></td>
                    <td><?php echo esc_html( $r->notas ?: '—' ); ?></td>
                    <td class="pf-actions">
                        <button class="button button-small pf-btn-edit" data-id="<?php echo esc_attr( $r->id ); ?>"><?php esc_html_e( 'Editar', 'plugin-fichaje' ); ?></button>
                        <button class="button button-small button-link-delete pf-btn-delete" data-id="<?php echo esc_attr( $r->id ); ?>"><?php esc_html_e( 'Eliminar', 'plugin-fichaje' ); ?></button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( $total_pages > 1 ) :
                $base_url = add_query_arg( [ 'page' => 'fichaje', 'pf_user' => $filter_user, 'pf_from' => $filter_from, 'pf_to' => $filter_to ], admin_url( 'admin.php' ) ); ?>
            <div class="pf-pagination" style="margin-top:12px">
                <?php for ( $p = 1; $p <= $total_pages; $p++ ) :
                    $cls = ( $p === $current_page ) ? 'button button-primary' : 'button'; ?>
                <a href="<?php echo esc_url( add_query_arg( 'pf_page', $p, $base_url ) ); ?>" class="<?php echo $cls; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="pf-card">
            <h2><?php esc_html_e( 'Añadir registro manual', 'plugin-fichaje' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Los registros manuales requieren un motivo obligatorio (requisito legal). Quedan auditados automáticamente.', 'plugin-fichaje' ); ?></p>
            <form id="pf-manual-form" class="pf-form">
                <div class="pf-form-row">
                    <label><?php esc_html_e( 'Trabajador *', 'plugin-fichaje' ); ?>
                        <select name="user_id" required>
                            <option value=""><?php esc_html_e( '— Selecciona —', 'plugin-fichaje' ); ?></option>
                            <?php foreach ( $all_users as $u ) : ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>"><?php echo esc_html( $u->display_name . ' <' . $u->user_email . '>' ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><?php esc_html_e( 'Hora de entrada *', 'plugin-fichaje' ); ?><input type="datetime-local" name="hora_entrada" required></label>
                    <label><?php esc_html_e( 'Hora de salida', 'plugin-fichaje' ); ?><input type="datetime-local" name="hora_salida"></label>
                </div>
                <div class="pf-form-row">
                    <label style="flex:1"><?php esc_html_e( 'Motivo * (obligatorio legal)', 'plugin-fichaje' ); ?>
                        <textarea name="motivo" rows="2" required placeholder="<?php esc_attr_e( 'Ej: El trabajador olvidó fichar entrada. Confirmado por supervisor.', 'plugin-fichaje' ); ?>"></textarea>
                    </label>
                    <label style="flex:1"><?php esc_html_e( 'Observaciones', 'plugin-fichaje' ); ?>
                        <textarea name="notas" rows="2"></textarea>
                    </label>
                </div>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar registro manual', 'plugin-fichaje' ); ?></button>
                <span class="pf-form-msg"></span>
            </form>
        </div>
    </div>

    <div id="pf-edit-modal" class="pf-modal" style="display:none">
        <div class="pf-modal-inner">
            <h2><?php esc_html_e( 'Editar registro', 'plugin-fichaje' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Cualquier cambio quedará registrado en el log de auditoría.', 'plugin-fichaje' ); ?></p>
            <form id="pf-edit-form">
                <input type="hidden" name="record_id" id="pf-edit-id">
                <label><?php esc_html_e( 'Hora entrada:', 'plugin-fichaje' ); ?><input type="datetime-local" name="hora_entrada" id="pf-edit-entrada"></label>
                <label><?php esc_html_e( 'Hora salida:', 'plugin-fichaje' ); ?><input type="datetime-local" name="hora_salida" id="pf-edit-salida"></label>
                <label><?php esc_html_e( 'Observaciones:', 'plugin-fichaje' ); ?><textarea name="notas" id="pf-edit-notas" rows="2"></textarea></label>
                <label><?php esc_html_e( 'Motivo de corrección * (obligatorio):', 'plugin-fichaje' ); ?><textarea name="motivo" id="pf-edit-motivo" rows="2" required placeholder="<?php esc_attr_e( 'Explica el motivo del cambio', 'plugin-fichaje' ); ?>"></textarea></label>
                <div class="pf-modal-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar cambios', 'plugin-fichaje' ); ?></button>
                    <button type="button" class="button pf-modal-close"><?php esc_html_e( 'Cancelar', 'plugin-fichaje' ); ?></button>
                </div>
                <span class="pf-form-msg"></span>
            </form>
        </div>
    </div>

    <div id="pf-delete-modal" class="pf-modal" style="display:none">
        <div class="pf-modal-inner">
            <h2><?php esc_html_e( '⚠️ Eliminar registro', 'plugin-fichaje' ); ?></h2>
            <p><?php esc_html_e( 'Esta acción quedará registrada en la auditoría. Indica el motivo.', 'plugin-fichaje' ); ?></p>
            <form id="pf-delete-form">
                <input type="hidden" name="record_id" id="pf-delete-id">
                <label><?php esc_html_e( 'Motivo * (obligatorio):', 'plugin-fichaje' ); ?><textarea name="motivo" id="pf-delete-motivo" rows="2" required></textarea></label>
                <div class="pf-modal-actions">
                    <button type="submit" class="button button-link-delete"><?php esc_html_e( 'Eliminar', 'plugin-fichaje' ); ?></button>
                    <button type="button" class="button pf-modal-close"><?php esc_html_e( 'Cancelar', 'plugin-fichaje' ); ?></button>
                </div>
                <span class="pf-form-msg"></span>
            </form>
        </div>
    </div>
    <?php
}

function pf_admin_employees_render() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( __( 'No tienes permisos.', 'plugin-fichaje' ) );

    if ( isset( $_POST['pf_save_employees'] ) && check_admin_referer( 'pf_employees_nonce' ) ) {
        $all_ids  = get_users( [ 'fields' => 'ID' ] );
        $selected = array_map( 'intval', (array) ( $_POST['pf_employees'] ?? [] ) );
        foreach ( $all_ids as $uid ) {
            if ( in_array( (int) $uid, $selected, true ) ) {
                update_user_meta( $uid, 'pf_empleado', '1' );
            } else {
                delete_user_meta( $uid, 'pf_empleado' );
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Lista de empleados actualizada correctamente.', 'plugin-fichaje' ) . '</p></div>';
    }

    global $wp_roles;
    $all_users = get_users( [ 'orderby' => 'display_name', 'order' => 'ASC' ] );
    ?>
    <div class="wrap pf-admin-wrap">
        <h1><span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'Gestión de Empleados', 'plugin-fichaje' ); ?></h1>
        <p><?php esc_html_e( 'Marca los usuarios de WordPress que son empleados. Solo ellos podrán ver y usar el widget de fichaje en la página pública. Los administradores siempre tienen acceso.', 'plugin-fichaje' ); ?></p>

        <form method="post">
            <?php wp_nonce_field( 'pf_employees_nonce' ); ?>
            <div class="pf-card">
                <table class="widefat striped pf-table">
                    <thead>
                        <tr>
                            <th style="width:40px"><input type="checkbox" id="pf-check-all" title="<?php esc_attr_e( 'Seleccionar todos', 'plugin-fichaje' ); ?>"></th>
                            <th><?php esc_html_e( 'Nombre', 'plugin-fichaje' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'plugin-fichaje' ); ?></th>
                            <th><?php esc_html_e( 'Rol en WordPress', 'plugin-fichaje' ); ?></th>
                            <th><?php esc_html_e( 'Estado', 'plugin-fichaje' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $all_users as $u ) :
                        $is_employee = get_user_meta( $u->ID, 'pf_empleado', true ) === '1';
                        $role_names  = [];
                        foreach ( $u->roles as $role_slug ) {
                            $role_names[] = isset( $wp_roles->roles[ $role_slug ] ) ? $wp_roles->roles[ $role_slug ]['name'] : $role_slug;
                        }
                    ?>
                    <tr>
                        <td><input type="checkbox" name="pf_employees[]" value="<?php echo esc_attr( $u->ID ); ?>" <?php checked( $is_employee ); ?>></td>
                        <td><strong><?php echo esc_html( $u->display_name ); ?></strong></td>
                        <td><?php echo esc_html( $u->user_email ); ?></td>
                        <td><?php echo esc_html( implode( ', ', $role_names ) ); ?></td>
                        <td><?php echo $is_employee ? '<span class="pf-badge pf-badge-active">✅ Empleado</span>' : '<span class="pf-badge">—</span>'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p>
                <button type="submit" name="pf_save_employees" value="1" class="button button-primary"><?php esc_html_e( 'Guardar lista de empleados', 'plugin-fichaje' ); ?></button>
            </p>
        </form>
    </div>
    <script>
    (function(){
        var checkAll = document.getElementById('pf-check-all');
        if ( checkAll ) {
            checkAll.addEventListener('change', function() {
                document.querySelectorAll('input[name="pf_employees[]"]').forEach(function(cb){ cb.checked = checkAll.checked; });
            });
        }
    })();
    </script>
    <?php
}

function pf_admin_audit_render() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( __( 'No tienes permisos.', 'plugin-fichaje' ) );
    global $wpdb;
    $aud     = $wpdb->prefix . PF_TABLE_AUD;
    $reg     = $wpdb->prefix . PF_TABLE_REG;
    $page    = max( 1, intval( $_GET['pf_page'] ?? 1 ) );
    $perpage = 50;
    $offset  = ( $page - 1 ) * $perpage;
    $total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$aud}" );
    $entries = $wpdb->get_results( $wpdb->prepare(
        "SELECT a.*, u.display_name AS admin_name, wu.display_name AS trabajador_name
         FROM {$aud} a
         LEFT JOIN {$wpdb->users} u  ON a.admin_user_id = u.ID
         LEFT JOIN {$reg} r ON a.registro_id = r.id
         LEFT JOIN {$wpdb->users} wu ON r.user_id = wu.ID
         ORDER BY a.created_at DESC LIMIT %d OFFSET %d",
        $perpage, $offset
    ) );
    ?>
    <div class="wrap pf-admin-wrap">
        <h1><?php esc_html_e( 'Log de Auditoría', 'plugin-fichaje' ); ?></h1>
        <p><?php esc_html_e( 'Registro inmutable de todas las correcciones manuales. Cumplimiento RDL 8/2019.', 'plugin-fichaje' ); ?></p>
        <?php if ( empty( $entries ) ) : ?>
        <p><?php esc_html_e( 'No hay entradas en el log de auditoría todavía.', 'plugin-fichaje' ); ?></p>
        <?php else : ?>
        <table class="widefat striped pf-table">
            <thead><tr>
                <th><?php esc_html_e( 'Fecha', 'plugin-fichaje' ); ?></th>
                <th><?php esc_html_e( 'Acción', 'plugin-fichaje' ); ?></th>
                <th><?php esc_html_e( 'Registro #', 'plugin-fichaje' ); ?></th>
                <th><?php esc_html_e( 'Trabajador', 'plugin-fichaje' ); ?></th>
                <th><?php esc_html_e( 'Campo', 'plugin-fichaje' ); ?></th>
                <th><?php esc_html_e( 'Valor anterior', 'plugin-fichaje' ); ?></th>
                <th><?php esc_html_e( 'Valor nuevo', 'plugin-fichaje' ); ?></th>
                <th><?php esc_html_e( 'Admin', 'plugin-fichaje' ); ?></th>
                <th><?php esc_html_e( 'Motivo', 'plugin-fichaje' ); ?></th>
                <th><?php esc_html_e( 'IP', 'plugin-fichaje' ); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ( $entries as $e ) : ?>
            <tr>
                <td><?php echo esc_html( mysql2date( 'd/m/Y H:i:s', $e->created_at ) ); ?></td>
                <td><span class="pf-tipo pf-tipo-<?php echo esc_attr( $e->accion ); ?>"><?php echo esc_html( strtoupper( $e->accion ) ); ?></span></td>
                <td>#<?php echo esc_html( $e->registro_id ); ?></td>
                <td><?php echo esc_html( $e->trabajador_name ?? '—' ); ?></td>
                <td><?php echo esc_html( $e->campo_cambiado ?? '—' ); ?></td>
                <td><code><?php echo esc_html( $e->valor_anterior ?? '—' ); ?></code></td>
                <td><code><?php echo esc_html( $e->valor_nuevo ?? '—' ); ?></code></td>
                <td><?php echo esc_html( $e->admin_name ); ?></td>
                <td><?php echo esc_html( $e->motivo ); ?></td>
                <td><?php echo esc_html( $e->ip_admin ); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}

function pf_admin_control_render() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( __( 'No tienes permisos.', 'plugin-fichaje' ) );

    if ( isset( $_POST['pf_save_control'] ) && check_admin_referer( 'pf_control_nonce' ) ) {
        $hora = sanitize_text_field( $_POST['pf_hora_inicio_turno'] ?? '' );
        if ( $hora === '' || preg_match( '/^\d{2}:\d{2}$/', $hora ) ) {
            update_option( 'pf_hora_inicio_turno', $hora );
        }
        $all_ids  = get_users( [ 'fields' => 'ID' ] );
        $selected = array_map( 'intval', (array) ( $_POST['pf_control_users'] ?? [] ) );
        foreach ( $all_ids as $uid ) {
            if ( in_array( (int) $uid, $selected, true ) ) update_user_meta( $uid, 'pf_control', '1' );
            else delete_user_meta( $uid, 'pf_control' );
        }
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Configuración de control guardada.', 'plugin-fichaje' ) . '</p></div>';
    }

    global $wp_roles;
    $hora_turno = get_option( 'pf_hora_inicio_turno', '' );
    $all_users  = get_users( [ 'orderby' => 'display_name', 'order' => 'ASC' ] );
    $base_url   = rest_url( 'fichaje/v1' );
    ?>
    <div class="wrap pf-admin-wrap">
        <h1><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Control y monitorización', 'plugin-fichaje' ); ?></h1>
        <p><?php esc_html_e( 'Configura quién puede consumir la API de monitorización y define la hora de turno para detectar llegadas tarde.', 'plugin-fichaje' ); ?></p>

        <form method="post">
            <?php wp_nonce_field( 'pf_control_nonce' ); ?>

            <div class="pf-card">
                <h2><?php esc_html_e( 'Hora de inicio de turno', 'plugin-fichaje' ); ?></h2>
                <p><?php esc_html_e( 'Si un trabajador ficha entrada después de esta hora se marca como llegada tarde. Deja vacío para no controlar.', 'plugin-fichaje' ); ?></p>
                <label>
                    <?php esc_html_e( 'Hora de turno:', 'plugin-fichaje' ); ?>
                    <input type="time" name="pf_hora_inicio_turno" value="<?php echo esc_attr( $hora_turno ); ?>">
                </label>
            </div>

            <div class="pf-card">
                <h2><?php esc_html_e( 'Usuarios con acceso a la API de control', 'plugin-fichaje' ); ?></h2>
                <p><?php esc_html_e( 'Estos usuarios podrán consultar y editar registros a través de los endpoints REST. Los administradores siempre tienen acceso.', 'plugin-fichaje' ); ?></p>
                <table class="widefat striped pf-table">
                    <thead>
                        <tr>
                            <th style="width:40px"><input type="checkbox" id="pf-control-check-all" title="<?php esc_attr_e( 'Seleccionar todos', 'plugin-fichaje' ); ?>"></th>
                            <th><?php esc_html_e( 'Nombre', 'plugin-fichaje' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'plugin-fichaje' ); ?></th>
                            <th><?php esc_html_e( 'Rol', 'plugin-fichaje' ); ?></th>
                            <th><?php esc_html_e( 'Estado', 'plugin-fichaje' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $all_users as $u ) :
                        $is_control = get_user_meta( $u->ID, 'pf_control', true ) === '1';
                        $roles      = array_map( fn( $r ) => $wp_roles->roles[ $r ]['name'] ?? $r, $u->roles );
                    ?>
                    <tr>
                        <td><input type="checkbox" name="pf_control_users[]" value="<?php echo esc_attr( $u->ID ); ?>" <?php checked( $is_control ); ?>></td>
                        <td><strong><?php echo esc_html( $u->display_name ); ?></strong></td>
                        <td><?php echo esc_html( $u->user_email ); ?></td>
                        <td><?php echo esc_html( implode( ', ', $roles ) ); ?></td>
                        <td><?php echo $is_control ? '<span class="pf-badge pf-badge-active">✅ Control</span>' : '<span class="pf-badge">—</span>'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p>
                <button type="submit" name="pf_save_control" value="1" class="button button-primary">
                    <?php esc_html_e( 'Guardar configuración', 'plugin-fichaje' ); ?>
                </button>
            </p>
        </form>

        <div class="pf-card">
            <h2><?php esc_html_e( 'Endpoints REST disponibles', 'plugin-fichaje' ); ?></h2>
            <p>
                <?php esc_html_e( 'Autenticación: usuario logueado con pf_control = 1 o administrador.', 'plugin-fichaje' ); ?>
                <?php esc_html_e( 'Enviar cabecera ', 'plugin-fichaje' ); ?><code>X-WP-Nonce: wp_create_nonce("wp_rest")</code>
            </p>
            <table class="widefat striped pf-table">
                <thead><tr><th><?php esc_html_e( 'Método', 'plugin-fichaje' ); ?></th><th>Endpoint</th><th><?php esc_html_e( 'Descripción', 'plugin-fichaje' ); ?></th><th><?php esc_html_e( 'Parámetros', 'plugin-fichaje' ); ?></th></tr></thead>
                <tbody>
                    <tr>
                        <td><code>GET</code></td>
                        <td><code><?php echo esc_html( $base_url . '/resumen-semanal' ); ?></code></td>
                        <td><?php esc_html_e( 'Resumen semanal: horas por día y trabajador, totales e incidencias', 'plugin-fichaje' ); ?></td>
                        <td><code>week=2025-W24</code></td>
                    </tr>
                    <tr>
                        <td><code>GET</code></td>
                        <td><code><?php echo esc_html( $base_url . '/empleados' ); ?></code></td>
                        <td><?php esc_html_e( 'Lista de empleados activos', 'plugin-fichaje' ); ?></td>
                        <td>—</td>
                    </tr>
                    <tr>
                        <td><code>GET</code></td>
                        <td><code><?php echo esc_html( $base_url . '/incidencias' ); ?></code></td>
                        <td><?php esc_html_e( 'Alertas activas: olvidados fichar, jornadas abiertas, llegadas tarde', 'plugin-fichaje' ); ?></td>
                        <td>—</td>
                    </tr>
                    <tr>
                        <td><code>PATCH</code></td>
                        <td><code><?php echo esc_html( $base_url . '/registros/{id}' ); ?></code></td>
                        <td><?php esc_html_e( 'Editar un registro (hora_entrada, hora_salida, notas). Queda auditado.', 'plugin-fichaje' ); ?></td>
                        <td><code>motivo*</code> · <code>hora_entrada</code> · <code>hora_salida</code> · <code>notas</code></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    (function(){
        var ca = document.getElementById('pf-control-check-all');
        if ( ca ) ca.addEventListener('change', function() {
            document.querySelectorAll('input[name="pf_control_users[]"]').forEach(function(cb){ cb.checked = ca.checked; });
        });
    })();
    </script>
    <?php
}
