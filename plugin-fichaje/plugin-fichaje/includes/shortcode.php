<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode( 'fichaje', 'pf_shortcode_render' );

function pf_shortcode_render( $atts ) {
    if ( ! is_user_logged_in() ) return '<p class="pf-notice">' . esc_html__( 'Debes iniciar sesión para fichar.', 'plugin-fichaje' ) . '</p>';
    $user_id  = get_current_user_id();
    $is_admin = current_user_can( 'manage_options' );
    if ( ! $is_admin && get_user_meta( $user_id, 'pf_empleado', true ) !== '1' ) {
        return '<p class="pf-notice">' . esc_html__( 'No tienes acceso al sistema de fichaje. Contacta con el administrador.', 'plugin-fichaje' ) . '</p>';
    }
    $atts     = shortcode_atts( [ 'show_team' => 'yes' ], $atts, 'fichaje' );
    $user     = wp_get_current_user();
    $status   = pf_get_user_status( $user_id );
    $is_in    = $status['is_clocked_in'];
    $open_rec = $status['record'];
    ob_start(); ?>
    <div class="pf-widget" id="pf-widget">
        <div class="pf-widget-header">
            <span class="pf-avatar">👤</span>
            <div>
                <strong><?php echo esc_html( $user->display_name ); ?></strong>
                <span class="pf-date"><?php echo esc_html( date_i18n( 'l, d \d\e F Y' ) ); ?></span>
            </div>
            <div class="pf-clock-live" id="pf-clock-live"></div>
        </div>

        <?php if ( $is_in && $open_rec ) : ?>
        <div class="pf-status pf-status-in">
            <?php esc_html_e( '🟢 En jornada desde las', 'plugin-fichaje' ); ?>
            <strong><?php echo esc_html( mysql2date( 'H:i', $open_rec->hora_entrada ) ); ?></strong>
            <span class="pf-elapsed" data-since="<?php echo esc_attr( strtotime( $open_rec->hora_entrada ) ); ?>"></span>
        </div>
        <?php else : ?>
        <div class="pf-status pf-status-out"><?php esc_html_e( '⚪ Fuera de jornada', 'plugin-fichaje' ); ?></div>
        <?php endif; ?>

        <div class="pf-btn-wrap">
            <?php if ( $is_in ) : ?>
            <button class="pf-btn pf-btn-out" id="pf-main-btn" data-action="pf_clock_out">
                <?php esc_html_e( '🔴 Fichar Salida', 'plugin-fichaje' ); ?>
            </button>
            <?php else : ?>
            <button class="pf-btn pf-btn-in" id="pf-main-btn" data-action="pf_clock_in">
                <?php esc_html_e( '🟢 Fichar Entrada', 'plugin-fichaje' ); ?>
            </button>
            <?php endif; ?>
        </div>

        <div id="pf-response-msg" class="pf-response-msg" style="display:none"></div>

        <?php
        $today_records = pf_get_records( [ 'user_id' => $user_id, 'fecha_from' => date( 'Y-m-d' ), 'fecha_to' => date( 'Y-m-d' ), 'orderby' => 'hora_entrada', 'order' => 'ASC', 'limit' => 20 ] );
        if ( ! empty( $today_records ) ) : ?>
        <div class="pf-today-records">
            <h4><?php esc_html_e( 'Tus registros de hoy', 'plugin-fichaje' ); ?></h4>
            <table class="pf-mini-table">
                <thead><tr><th><?php esc_html_e( 'Entrada', 'plugin-fichaje' ); ?></th><th><?php esc_html_e( 'Salida', 'plugin-fichaje' ); ?></th><th><?php esc_html_e( 'Duración', 'plugin-fichaje' ); ?></th></tr></thead>
                <tbody>
                <?php foreach ( $today_records as $r ) : ?>
                <tr>
                    <td><?php echo esc_html( mysql2date( 'H:i:s', $r->hora_entrada ) ); ?></td>
                    <td><?php echo $r->hora_salida ? esc_html( mysql2date( 'H:i:s', $r->hora_salida ) ) : '<em>—</em>'; ?></td>
                    <td><?php echo esc_html( pf_format_duration( $r->hora_entrada, $r->hora_salida ) ); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif;

        if ( $is_admin && 'yes' === $atts['show_team'] ) :
            $team = pf_get_records( [ 'fecha_from' => date( 'Y-m-d' ), 'fecha_to' => date( 'Y-m-d' ), 'limit' => 100, 'orderby' => 'hora_entrada', 'order' => 'ASC' ] ); ?>
        <div class="pf-team-status">
            <h4><?php esc_html_e( '👥 Estado del equipo hoy', 'plugin-fichaje' ); ?></h4>
            <?php if ( empty( $team ) ) : ?>
            <p><?php esc_html_e( 'Nadie ha fichado hoy todavía.', 'plugin-fichaje' ); ?></p>
            <?php else : ?>
            <table class="pf-mini-table">
                <thead><tr><th><?php esc_html_e( 'Trabajador', 'plugin-fichaje' ); ?></th><th><?php esc_html_e( 'Entrada', 'plugin-fichaje' ); ?></th><th><?php esc_html_e( 'Salida', 'plugin-fichaje' ); ?></th><th><?php esc_html_e( 'Estado', 'plugin-fichaje' ); ?></th></tr></thead>
                <tbody>
                <?php foreach ( $team as $r ) : ?>
                <tr>
                    <td><?php echo esc_html( $r->display_name ); ?></td>
                    <td><?php echo esc_html( mysql2date( 'H:i', $r->hora_entrada ) ); ?></td>
                    <td><?php echo $r->hora_salida ? esc_html( mysql2date( 'H:i', $r->hora_salida ) ) : '—'; ?></td>
                    <td><?php echo $r->hora_salida ? '<span class="pf-dot">✅ Finalizada</span>' : '<span class="pf-dot">🟢 En jornada</span>'; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=fichaje' ) ); ?>" class="pf-link">→ <?php esc_html_e( 'Gestionar en el panel de administración', 'plugin-fichaje' ); ?></a></p>
        </div>
        <?php endif; ?>
    </div>
    <?php return ob_get_clean();
}
