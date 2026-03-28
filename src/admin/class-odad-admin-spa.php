<?php

/**
 * Registers the HR Suite SPA as a WordPress admin page.
 *
 * Serves the Vue app built at src/odad-hrms/view/dist/.
 * User is authenticated via WordPress session; X-WP-Nonce is passed via
 * window.wphrApi. Script is loaded as type="module" (Vite ESM output).
 */
class ODAD_Admin_SPA {

	private const DIST_REL = 'src/odad-hrms/view/dist';

	public function register(): void {
		add_action( 'admin_menu',            [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_spa' ] );
		add_action( 'admin_head',            [ $this, 'hide_update_nag' ] );
		add_filter( 'script_loader_tag',     [ $this, 'add_module_type' ], 10, 3 );
	}

	public function add_menu_page(): void {
		add_menu_page(
			__( 'HR Suite', 'wp-odata-suite' ),
			__( 'HR Suite', 'wp-odata-suite' ),
			'read',
			'odad-hr-spa',
			[ $this, 'render_spa_page' ],
			'dashicons-groups',
			30
		);
	}

	public function render_spa_page(): void {
		?>
		<div class="wrap odad-hr-wrap">
			<div id="app"></div>
		</div>
		<?php
	}

	public function enqueue_spa( string $hook ): void {
		if ( 'toplevel_page_odad-hr-spa' !== $hook ) {
			return;
		}

		$assets   = $this->get_spa_assets();
		$dist_url = plugins_url( self::DIST_REL, ODAD_PLUGIN_FILE );
		$dist_dir = plugin_dir_path( ODAD_PLUGIN_FILE ) . self::DIST_REL;

		if ( empty( $assets ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p>HR Suite SPA: build not found. Run <code>npm run build</code> inside the <code>src/odad-hrms/view/</code> directory.</p></div>';
			} );
			return;
		}

		if ( ! empty( $assets['css'] ) && file_exists( $dist_dir . '/' . $assets['css'] ) ) {
			wp_enqueue_style(
				'odad-hr-spa',
				$dist_url . '/' . $assets['css'],
				[],
				ODAD_VERSION
			);

			// Offset Vue fixed layout for WP admin bar and sidebar.
			$layout_overrides = '
				.wp-hr-app { min-height: 60vh; }
				.wrap.odad-hr-wrap { margin: 0 !important; }
				.wrap h1.wp-heading-inline { display: none !important; }
				#wpcontent { padding-left: 0 !important; }
				.odad-hr-wrap .layout-topbar {
					top: 32px !important;
					left: 160px !important;
					width: calc(100% - 160px) !important;
				}
				.odad-hr-wrap .layout-sidebar {
					top: calc(32px + 4rem) !important;
					left: 160px !important;
					height: calc(100vh - 32px - 4rem) !important;
				}
				.odad-hr-wrap .layout-main-container {
					padding-top: 4rem !important;
					margin-left: calc(160px + 8rem) !important;
				}
				@media screen and (max-width: 991px) {
					.odad-hr-wrap .layout-main-container { margin-left: 160px !important; }
				}
				input.p-inputtext,
				input.p-datepicker-input {
					height: 34.2px !important;
					border: 1px solid var(--p-select-border-color);
					border-radius: var(--p-select-border-radius);
				}
			';
			wp_add_inline_style( 'odad-hr-spa', $layout_overrides );
		}

		if ( ! empty( $assets['js'] ) && file_exists( $dist_dir . '/' . $assets['js'] ) ) {
			wp_enqueue_script(
				'odad-hr-spa',
				$dist_url . '/' . $assets['js'],
				[],
				ODAD_VERSION,
				true
			);

			wp_localize_script(
				'odad-hr-spa',
				'wphrApi',
				[
					'root'      => esc_url_raw( rest_url() ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'namespace' => 'odata/v4',
					'activeModules' => [
						'leave'   => is_plugin_active( 'wp-hr-leave/wp-hr-leave.php' ),
						'payroll' => is_plugin_active( 'wp-hr-payroll/wp-hr-payroll.php' ),
						'recruit' => is_plugin_active( 'wp-hr-recruit/wp-hr-recruit.php' ),
					],
					'dateFormat'      => get_option( 'date_format', 'Y-m-d' ),
					'timeFormat'      => get_option( 'time_format', 'H:i' ),
					'timezone'        => wp_timezone_string(),
					'gmtOffset'       => (float) get_option( 'gmt_offset', 0 ),
					'primeDateFormat' => $this->php_to_primevue_date_format( get_option( 'date_format', 'Y-m-d' ) ),
				]
			);
		}
	}

	/**
	 * Discover hashed JS/CSS filenames by parsing dist/index.html.
	 *
	 * Extracts paths from the <script type="module" src="..."> and
	 * <link rel="stylesheet" href="..."> tags Vite writes into index.html.
	 * Returns paths relative to the dist directory (e.g. "assets/index-abc.js").
	 *
	 * @return array{ js?: string, css?: string }
	 */
	private function get_spa_assets(): array {
		$dist_dir  = plugin_dir_path( ODAD_PLUGIN_FILE ) . self::DIST_REL;
		$html_path = $dist_dir . '/index.html';

		if ( ! file_exists( $html_path ) ) {
			return [];
		}

		$html = (string) file_get_contents( $html_path );
		$out  = [];

		// Extract JS: <script type="module" ... src="/...dist/assets/index-*.js">
		if ( preg_match( '#<script[^>]+type=["\']module["\'][^>]+src=["\'][^"\']*?(assets/[^"\']+\.js)["\']#i', $html, $m ) ) {
			$out['js'] = $m[1];
		}

		// Extract CSS: <link ... href="/...dist/assets/index-*.css">
		if ( preg_match( '#<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\'][^"\']*?(assets/[^"\']+\.css)["\']#i', $html, $m ) ) {
			$out['css'] = $m[1];
		}

		return $out;
	}

	/**
	 * Convert a PHP date format string to PrimeVue's date format notation.
	 */
	private function php_to_primevue_date_format( string $php_format ): string {
		$map = [
			'd' => 'dd',
			'j' => 'd',
			'm' => 'mm',
			'n' => 'm',
			'Y' => 'yy',
			'y' => 'y',
		];
		return strtr( $php_format, $map );
	}

	/**
	 * Load the SPA script as an ES module (required for Vite output).
	 */
	public function add_module_type( string $tag, string $handle, string $src ): string {
		if ( 'odad-hr-spa' === $handle ) {
			return '<script type="module" src="' . esc_url( $src ) . '" id="odad-hr-spa-js"></script>' . "\n";
		}
		return $tag;
	}

	/**
	 * Hide the WordPress update nag on the HR Suite page.
	 */
	public function hide_update_nag(): void {
		$screen = get_current_screen();
		if ( $screen && 'toplevel_page_odad-hr-spa' === $screen->id ) {
			remove_action( 'admin_notices', 'update_nag', 3 );
			remove_action( 'network_admin_notices', 'update_nag', 3 );
		}
	}
}
