<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sistema de logging para debugging del plugin.
 */
class WPDM_Logger {

	/**
	 * Opción para habilitar/deshabilitar logging.
	 */
	const OPTION_ENABLED = 'wpdm_logger_enabled';

	/**
	 * Opción para retención de logs (en horas).
	 */
	const OPTION_RETENTION_HOURS = 'wpdm_logger_retention_hours';

	/**
	 * Nombre de la tabla de logs.
	 */
	const TABLE_NAME = 'wpdm_logs';

	/**
	 * Inicializar el sistema de logging.
	 */
	public static function init() {
		// Crear tabla de logs si no existe.
		self::create_log_table();

		// Programar limpieza automática de logs antiguos.
		if ( ! wp_next_scheduled( 'wpdm_cleanup_old_logs' ) ) {
			wp_schedule_event( time(), 'hourly', 'wpdm_cleanup_old_logs' );
		}
		add_action( 'wpdm_cleanup_old_logs', array( __CLASS__, 'cleanup_old_logs' ) );

		// Agregar página de administración.
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Crear tabla de logs en la base de datos.
	 */
	private static function create_log_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			level varchar(20) NOT NULL,
			context varchar(100) NOT NULL,
			message text NOT NULL,
			data longtext,
			PRIMARY KEY  (id),
			KEY timestamp (timestamp),
			KEY level (level),
			KEY context (context)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Registrar un log.
	 *
	 * @param string $level   Nivel: 'debug', 'info', 'warning', 'error'
	 * @param string $context Contexto (nombre de la clase o función)
	 * @param string $message Mensaje
	 * @param mixed  $data    Datos adicionales (se serializarán)
	 */
	public static function log( $level, $context, $message, $data = null ) {
		// Verificar si el logging está habilitado.
		if ( ! self::is_enabled() ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$data_serialized = null;
		if ( $data !== null ) {
			$data_serialized = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		}

		$wpdb->insert(
			$table_name,
			array(
				'level'   => sanitize_text_field( $level ),
				'context' => sanitize_text_field( $context ),
				'message' => sanitize_text_field( $message ),
				'data'    => $data_serialized,
			),
			array( '%s', '%s', '%s', '%s' )
		);

		// También enviar a error_log si está en modo debug.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log_message = sprintf(
				'[WPDM %s] %s: %s',
				strtoupper( $level ),
				$context,
				$message
			);
			if ( $data !== null ) {
				$log_message .= ' | Data: ' . $data_serialized;
			}
			error_log( $log_message );
		}
	}

	/**
	 * Log de debug.
	 *
	 * @param string $context
	 * @param string $message
	 * @param mixed  $data
	 */
	public static function debug( $context, $message, $data = null ) {
		self::log( 'debug', $context, $message, $data );
	}

	/**
	 * Log de información.
	 *
	 * @param string $context
	 * @param string $message
	 * @param mixed  $data
	 */
	public static function info( $context, $message, $data = null ) {
		self::log( 'info', $context, $message, $data );
	}

	/**
	 * Log de advertencia.
	 *
	 * @param string $context
	 * @param string $message
	 * @param mixed  $data
	 */
	public static function warning( $context, $message, $data = null ) {
		self::log( 'warning', $context, $message, $data );
	}

	/**
	 * Log de error.
	 *
	 * @param string $context
	 * @param string $message
	 * @param mixed  $data
	 */
	public static function error( $context, $message, $data = null ) {
		self::log( 'error', $context, $message, $data );
	}

	/**
	 * Obtener logs.
	 *
	 * @param array $args Argumentos de consulta.
	 * @return array
	 */
	public static function get_logs( $args = array() ) {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$defaults = array(
			'limit'   => 100,
			'offset'  => 0,
			'level'   => null,
			'context' => null,
			'hours'   => 24, // Últimas 24 horas por defecto
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$where_values = array();

		// Filtrar por nivel.
		if ( $args['level'] ) {
			$where[] = 'level = %s';
			$where_values[] = $args['level'];
		}

		// Filtrar por contexto.
		if ( $args['context'] ) {
			$where[] = 'context = %s';
			$where_values[] = $args['context'];
		}

		// Filtrar por horas.
		if ( $args['hours'] > 0 ) {
			$where[] = 'timestamp >= DATE_SUB(NOW(), INTERVAL %d HOUR)';
			$where_values[] = $args['hours'];
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY timestamp DESC LIMIT %d OFFSET %d";
		$where_values[] = $args['limit'];
		$where_values[] = $args['offset'];

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Contar logs.
	 *
	 * @param array $args Argumentos de consulta.
	 * @return int
	 */
	public static function count_logs( $args = array() ) {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$defaults = array(
			'level'   => null,
			'context' => null,
			'hours'   => 24,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$where_values = array();

		if ( $args['level'] ) {
			$where[] = 'level = %s';
			$where_values[] = $args['level'];
		}

		if ( $args['context'] ) {
			$where[] = 'context = %s';
			$where_values[] = $args['context'];
		}

		if ( $args['hours'] > 0 ) {
			$where[] = 'timestamp >= DATE_SUB(NOW(), INTERVAL %d HOUR)';
			$where_values[] = $args['hours'];
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Borrar logs antiguos.
	 *
	 * @param int $hours Horas de retención.
	 * @return int Número de registros borrados.
	 */
	public static function cleanup_old_logs( $hours = null ) {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		if ( $hours === null ) {
			$hours = (int) get_option( self::OPTION_RETENTION_HOURS, 24 );
		}

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d HOUR)",
				$hours
			)
		);

		return $deleted;
	}

	/**
	 * Borrar todos los logs.
	 *
	 * @return int Número de registros borrados.
	 */
	public static function clear_all_logs() {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		return $wpdb->query( "TRUNCATE TABLE $table_name" );
	}

	/**
	 * Verificar si el logging está habilitado.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		// Por defecto deshabilitado en producción para versión estable.
		return (bool) get_option( self::OPTION_ENABLED, false );
	}

	/**
	 * Agregar menú de administración.
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'WPDM Logs', 'woo-prices-dynamics-makito' ),
			__( 'WPDM Logs', 'woo-prices-dynamics-makito' ),
			'manage_woocommerce',
			'wpdm-logs',
			array( __CLASS__, 'render_logs_page' )
		);
	}

	/**
	 * Registrar configuraciones.
	 */
	public static function register_settings() {
		register_setting( 'wpdm_logger_settings', self::OPTION_ENABLED );
		register_setting( 'wpdm_logger_settings', self::OPTION_RETENTION_HOURS );
	}

	/**
	 * Renderizar página de logs.
	 */
	public static function render_logs_page() {
		// Manejar acciones.
		if ( isset( $_POST['wpdm_clear_logs'] ) && check_admin_referer( 'wpdm_clear_logs' ) ) {
			$deleted = self::clear_all_logs();
			echo '<div class="notice notice-success"><p>' . sprintf(
				esc_html__( 'Se borraron %d registros de log.', 'woo-prices-dynamics-makito' ),
				$deleted
			) . '</p></div>';
		}

		if ( isset( $_POST['wpdm_save_settings'] ) && check_admin_referer( 'wpdm_save_settings' ) ) {
			update_option( self::OPTION_ENABLED, isset( $_POST['wpdm_logger_enabled'] ) ? 1 : 0 );
			update_option( self::OPTION_RETENTION_HOURS, absint( $_POST['wpdm_logger_retention_hours'] ) );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Configuración guardada.', 'woo-prices-dynamics-makito' ) . '</p></div>';
		}

		$enabled = self::is_enabled();
		$retention_hours = (int) get_option( self::OPTION_RETENTION_HOURS, 24 );
		$logs = self::get_logs( array( 'hours' => $retention_hours ) );
		$total_logs = self::count_logs( array( 'hours' => $retention_hours ) );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WPDM Logs - Sistema de Debugging', 'woo-prices-dynamics-makito' ); ?></h1>

			<div class="wpdm-logs-container" style="margin-top: 20px; max-width: 100%;">
				<!-- Configuración -->
				<div class="card" style="margin-bottom: 20px; max-width: 100%; width: 100%;">
					<h2><?php esc_html_e( 'Configuración', 'woo-prices-dynamics-makito' ); ?></h2>
					<form method="post">
						<?php wp_nonce_field( 'wpdm_save_settings' ); ?>
						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Habilitar Logging', 'woo-prices-dynamics-makito' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="wpdm_logger_enabled" value="1" <?php checked( $enabled ); ?>>
										<?php esc_html_e( 'Activar sistema de logging', 'woo-prices-dynamics-makito' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Retención de Logs', 'woo-prices-dynamics-makito' ); ?></th>
								<td>
									<input type="number" name="wpdm_logger_retention_hours" value="<?php echo esc_attr( $retention_hours ); ?>" min="1" max="168" style="width: 100px;">
									<span class="description"><?php esc_html_e( 'Horas (máximo 168 = 7 días)', 'woo-prices-dynamics-makito' ); ?></span>
								</td>
							</tr>
						</table>
						<?php submit_button( __( 'Guardar Configuración', 'woo-prices-dynamics-makito' ), 'primary', 'wpdm_save_settings' ); ?>
					</form>
				</div>

				<!-- Estadísticas -->
				<div class="card" style="margin-bottom: 20px; max-width: 100%; width: 100%;">
					<h2><?php esc_html_e( 'Estadísticas', 'woo-prices-dynamics-makito' ); ?></h2>
					<p>
						<strong><?php esc_html_e( 'Total de logs (últimas', 'woo-prices-dynamics-makito' ); ?> <?php echo esc_html( $retention_hours ); ?> <?php esc_html_e( 'horas):', 'woo-prices-dynamics-makito' ); ?></strong>
						<?php echo esc_html( $total_logs ); ?>
					</p>
					<form method="post" style="margin-top: 10px;">
						<?php wp_nonce_field( 'wpdm_clear_logs' ); ?>
						<?php submit_button( __( 'Borrar Todos los Logs', 'woo-prices-dynamics-makito' ), 'delete', 'wpdm_clear_logs', false ); ?>
					</form>
				</div>

				<!-- Lista de Logs -->
				<div class="card" style="max-width: 100%; width: 100%;">
					<h2><?php esc_html_e( 'Logs Recientes', 'woo-prices-dynamics-makito' ); ?></h2>
					<?php if ( empty( $logs ) ) : ?>
						<p><?php esc_html_e( 'No hay logs registrados.', 'woo-prices-dynamics-makito' ); ?></p>
					<?php else : ?>
						<div style="overflow-x: auto;">
						<table class="wp-list-table widefat fixed striped" style="width: 100%; table-layout: auto;">
							<thead>
								<tr>
									<th style="width: 150px;"><?php esc_html_e( 'Fecha/Hora', 'woo-prices-dynamics-makito' ); ?></th>
									<th style="width: 80px;"><?php esc_html_e( 'Nivel', 'woo-prices-dynamics-makito' ); ?></th>
									<th style="width: 150px;"><?php esc_html_e( 'Contexto', 'woo-prices-dynamics-makito' ); ?></th>
									<th><?php esc_html_e( 'Mensaje', 'woo-prices-dynamics-makito' ); ?></th>
									<th style="width: 100px;"><?php esc_html_e( 'Datos', 'woo-prices-dynamics-makito' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $logs as $log ) : ?>
									<?php
									$level_class = 'wpdm-log-' . esc_attr( $log['level'] );
									$data_decoded = null;
									if ( ! empty( $log['data'] ) ) {
										$data_decoded = json_decode( $log['data'], true );
									}
									?>
									<tr class="<?php echo esc_attr( $level_class ); ?>">
										<td><?php echo esc_html( $log['timestamp'] ); ?></td>
										<td>
											<span class="wpdm-badge wpdm-badge-<?php echo esc_attr( $log['level'] ); ?>">
												<?php echo esc_html( strtoupper( $log['level'] ) ); ?>
											</span>
										</td>
										<td><code><?php echo esc_html( $log['context'] ); ?></code></td>
										<td><?php echo esc_html( $log['message'] ); ?></td>
										<td>
											<?php if ( $data_decoded ) : ?>
												<button type="button" class="button button-small wpdm-view-data" data-log-id="<?php echo esc_attr( $log['id'] ); ?>">
													<?php esc_html_e( 'Ver Datos', 'woo-prices-dynamics-makito' ); ?>
												</button>
												<pre class="wpdm-log-data" id="wpdm-data-<?php echo esc_attr( $log['id'] ); ?>" style="display: none; max-height: 200px; overflow: auto; background: #f5f5f5; padding: 10px; margin-top: 5px;"><?php echo esc_html( wp_json_encode( $data_decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
											<?php else : ?>
												—
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<style>
			.wpdm-logs-container {
				max-width: 100% !important;
				width: 100% !important;
			}
			.wpdm-logs-container .card {
				max-width: 100% !important;
				width: 100% !important;
				box-sizing: border-box;
			}
			.wpdm-logs-container table {
				width: 100% !important;
				table-layout: auto !important;
			}
			.wpdm-logs-container table th,
			.wpdm-logs-container table td {
				word-wrap: break-word;
				overflow-wrap: break-word;
			}
			.wpdm-badge {
				display: inline-block;
				padding: 3px 8px;
				border-radius: 3px;
				font-size: 11px;
				font-weight: 600;
			}
			.wpdm-badge-debug { background: #e0e0e0; color: #333; }
			.wpdm-badge-info { background: #2196F3; color: #fff; }
			.wpdm-badge-warning { background: #ff9800; color: #fff; }
			.wpdm-badge-error { background: #f44336; color: #fff; }
			.wpdm-log-error { background-color: #ffebee !important; }
			.wpdm-log-warning { background-color: #fff3e0 !important; }
			.wpdm-log-data {
				max-width: 100%;
				overflow-x: auto;
				word-wrap: break-word;
			}
		</style>

		<script>
		jQuery(document).ready(function($) {
			$('.wpdm-view-data').on('click', function() {
				var logId = $(this).data('log-id');
				var $data = $('#wpdm-data-' + logId);
				if ($data.is(':visible')) {
					$data.hide();
					$(this).text('<?php echo esc_js( __( 'Ver Datos', 'woo-prices-dynamics-makito' ) ); ?>');
				} else {
					$data.show();
					$(this).text('<?php echo esc_js( __( 'Ocultar Datos', 'woo-prices-dynamics-makito' ) ); ?>');
				}
			});
		});
		</script>
		<?php
	}
}

