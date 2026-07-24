<?php
/**
 * Clean Sweep Admin
 *
 * @package Clean_Sweep
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Clean_Sweep_Admin
 */
class Clean_Sweep_Admin {

	/**
	 * Backup directory path.
     *
     * @var string
     */
	private static $backup_dir = '';

	/**
	 * Add admin page.
     */
	public static function add_menu() {
		add_management_page(
			esc_html__( 'Clean Sweep', 'clean-sweep' ),
			esc_html__( 'Clean Sweep', 'clean-sweep' ),
			'manage_options',
			'clean-sweep',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue assets.
     *
     * @param string $hook Current admin page hook.
     */
	public static function enqueue_assets( $hook ) {
		if ( 'tools_page_clean-sweep' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'clean-sweep-admin', CLEAN_SWEEP_URL . 'assets/css/admin.css', array(), CLEAN_SWEEP_VERSION );
		wp_enqueue_script( 'clean-sweep-admin', CLEAN_SWEEP_URL . 'assets/js/admin.js', array(), CLEAN_SWEEP_VERSION, true );
		wp_localize_script(
			'clean-sweep-admin',
			'clean_sweep_i18n',
			array(
				'selectAll'       => __( 'Select All', 'clean-sweep' ),
				'deselectAll'     => __( 'Deselect All', 'clean-sweep' ),
				'selected'        => __( '%d selected', 'clean-sweep' ),
				'nothingSelected' => __( 'Please select at least one item.', 'clean-sweep' ),
			)
		);
	}

	/**
	 * Get backup directory.
     *
     * @return string
     */
	private static function backup_dir() {
		if ( empty( self::$backup_dir ) ) {
			self::$backup_dir = WP_CONTENT_DIR . '/clean-sweep-backups';
			if ( ! file_exists( self::$backup_dir ) ) {
				wp_mkdir_p( self::$backup_dir );
				@file_put_contents( self::$backup_dir . '/index.php', "<?php\n// Silence is golden.\n" );
				@file_put_contents( self::$backup_dir . '/.htaccess', "<Files *>\nOrder allow,deny\nDeny from all\n</Files>\n" );
			}
		}
		return self::$backup_dir;
	}

	/**
	 * Handle actions.
     */
	public static function handle_actions() {
		if ( ! isset( $_REQUEST['page'] ) || 'clean-sweep' !== $_REQUEST['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'clean-sweep' ) );
		}

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'clean_sweep_action' ) ) {
			return;
		}

		$action = isset( $_REQUEST['cs_action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cs_action'] ) ) : '';
		$type   = isset( $_REQUEST['cs_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cs_type'] ) ) : '';

		if ( ! in_array( $type, array( 'themes', 'plugins', 'media', 'database' ), true ) ) {
			return;
		}

		$selected = isset( $_REQUEST['cs_items'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_REQUEST['cs_items'] ) ) : array();
		$result   = array();

		switch ( $action ) {
			case 'delete':
				if ( 'themes' === $type ) {
					$result = self::delete_themes( $selected );
				} elseif ( 'plugins' === $type ) {
					$result = self::delete_plugins( $selected );
				} elseif ( 'media' === $type ) {
					$result = self::delete_media( $selected );
				} elseif ( 'database' === $type ) {
					$total_ok  = 0;
					$total_msg = array();
					$expired_only = isset( $_REQUEST['cs_expired_only'] ) ? true : false;
					foreach ( $selected as $item ) {
						if ( in_array( $item, array( 'revisions', 'autodrafts', 'trash', 'spam', 'orphan_meta' ), true ) ) {
							$r = self::cleanup_database( $item, array( $item ) );
							if ( $r['success'] ) {
								$total_ok++;
							}
							$total_msg[] = $r['message'];
						} elseif ( 'transients' === $item ) {
							$r = self::cleanup_transients( $expired_only );
							if ( $r['success'] ) {
								$total_ok++;
							}
							$total_msg[] = $r['message'];
						}
					}
					$result = array(
						'success' => $total_ok > 0,
						'message' => implode( ' ', $total_msg ),
					);
				}
				break;
			case 'backup':
				if ( 'themes' === $type ) {
					$result = self::backup_themes( $selected );
				} elseif ( 'plugins' === $type ) {
					$result = self::backup_plugins( $selected );
				}
				break;
		}

		if ( ! empty( $result ) ) {
			add_settings_error( 'clean_sweep', 'clean_sweep_notice', $result['message'], $result['success'] ? 'updated' : 'error' );
		}
	}

	/**
	 * Render admin page.
     */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'clean-sweep' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'themes';
		if ( ! in_array( $tab, array( 'themes', 'plugins', 'media', 'database' ), true ) ) {
			$tab = 'themes';
		}

		$search = isset( $_GET['cs_search'] ) ? sanitize_text_field( wp_unslash( $_GET['cs_search'] ) ) : '';
		?>
		<div class="wrap clean-sweep-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors( 'clean_sweep' ); ?>

			<nav class="clean-sweep-tabs">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'themes' ) ); ?>" class="<?php echo 'themes' === $tab ? 'active' : ''; ?>"><?php esc_html_e( 'Themes', 'clean-sweep' ); ?></a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'plugins' ) ); ?>" class="<?php echo 'plugins' === $tab ? 'active' : ''; ?>"><?php esc_html_e( 'Plugins', 'clean-sweep' ); ?></a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'media' ) ); ?>" class="<?php echo 'media' === $tab ? 'active' : ''; ?>"><?php esc_html_e( 'Media', 'clean-sweep' ); ?></a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'database' ) ); ?>" class="<?php echo 'database' === $tab ? 'active' : ''; ?>"><?php esc_html_e( 'Database', 'clean-sweep' ); ?></a>
			</nav>

			<form method="get" class="clean-sweep-search">
				<input type="hidden" name="page" value="clean-sweep">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
				<input type="search" name="cs_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search...', 'clean-sweep' ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Search', 'clean-sweep' ); ?></button>
			</form>

			<?php self::render_summary( $tab, $search ); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=clean-sweep&tab=' . $tab ) ); ?>" class="clean-sweep-form">
				<?php wp_nonce_field( 'clean_sweep_action' ); ?>
				<input type="hidden" name="cs_type" value="<?php echo esc_attr( $tab ); ?>">
				<input type="hidden" name="cs_action" value="delete">

				<div class="clean-sweep-actions">
					<button type="submit" class="button button-primary" onclick="return confirm('<?php esc_attr_e( 'This will backup and delete selected items. Continue?', 'clean-sweep' ); ?>')">
						<?php esc_html_e( 'Backup & Delete Selected', 'clean-sweep' ); ?>
					</button>
					<span class="clean-sweep-selected-count"></span>
				</div>

				<div class="clean-sweep-grid">
					<?php
					switch ( $tab ) {
						case 'themes':
							self::render_themes( $search );
							break;
							case 'plugins':
								self::render_plugins( $search );
								break;
							case 'media':
								self::render_media( $search );
								break;
							case 'database':
								self::render_database();
								break;
					}
					?>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render summary dashboard.
     *
     * @param string $tab    Current tab.
     * @param string $search Search query.
     */
	private static function render_summary( $tab, $search ) {
		global $wpdb;

		$cards = array();
		switch ( $tab ) {
			case 'themes':
				$themes   = wp_get_themes();
				$active   = get_stylesheet();
				$total    = count( $themes );
				$inactive = 0;
				$size     = 0;
				foreach ( $themes as $slug => $theme ) {
					if ( $search && false === stripos( $theme->get( 'Name' ), $search ) && false === stripos( $slug, $search ) ) {
						continue;
					}
					$s = self::dir_size( $theme->get_stylesheet_directory() );
					$size += $s;
					if ( $slug !== $active ) {
						$inactive++;
					}
				}
				$cards = array(
					'total'    => array( 'label' => __( 'Total Themes', 'clean-sweep' ), 'value' => $total ),
					'inactive' => array( 'label' => __( 'Inactive', 'clean-sweep' ), 'value' => $inactive ),
					'size'     => array( 'label' => __( 'Total Size', 'clean-sweep' ), 'value' => size_format( $size ) ),
				);
				break;
			case 'plugins':
				$plugins        = get_plugins();
				$active_plugins = wp_get_active_and_valid_plugins();
				$active_slugs   = array_map( 'plugin_basename', $active_plugins );
				$total          = count( $plugins );
				$inactive       = 0;
				$size           = 0;
				foreach ( $plugins as $slug => $data ) {
					if ( $search && false === stripos( $data['Name'], $search ) && false === stripos( $slug, $search ) ) {
						continue;
					}
					$path = WP_PLUGIN_DIR . '/' . $slug;
					$s    = is_dir( $path ) ? self::dir_size( $path ) : ( file_exists( $path ) ? filesize( $path ) : 0 );
					$size += $s;
					if ( ! in_array( $slug, $active_slugs, true ) ) {
						$inactive++;
					}
				}
				$cards = array(
					'total'    => array( 'label' => __( 'Total Plugins', 'clean-sweep' ), 'value' => $total ),
					'inactive' => array( 'label' => __( 'Inactive', 'clean-sweep' ), 'value' => $inactive ),
					'size'     => array( 'label' => __( 'Total Size', 'clean-sweep' ), 'value' => size_format( $size ) ),
				);
				break;
			case 'media':
				$query = new WP_Query(
					array(
						'post_type'      => 'attachment',
						'post_status'    => 'inherit',
						'post_mime_type' => 'image',
						'posts_per_page' => -1,
						's'              => $search,
					)
				);
				$total = $query->found_posts;
				$size  = 0;
				foreach ( $query->posts as $post ) {
					$meta = wp_get_attachment_metadata( $post->ID );
					if ( ! empty( $meta['filesize'] ) ) {
						$size += (int) $meta['filesize'];
					} else {
						$file = get_attached_file( $post->ID );
						$size += file_exists( $file ) ? filesize( $file ) : 0;
					}
				}
				$cards = array(
					'total' => array( 'label' => __( 'Total Images', 'clean-sweep' ), 'value' => $total ),
					'size'  => array( 'label' => __( 'Total Size', 'clean-sweep' ), 'value' => size_format( $size ) ),
				);
				break;
			case 'database':
				$revisions   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );
				$autodrafts  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
				$trash       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" );
				$spam        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
				$orphan_meta = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );
				$expired_ts  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < " . time() );
				$cards       = array(
					'revisions'   => array( 'label' => __( 'Revisions', 'clean-sweep' ), 'value' => $revisions ),
					'autodrafts'  => array( 'label' => __( 'Auto-Drafts', 'clean-sweep' ), 'value' => $autodrafts ),
					'trash'       => array( 'label' => __( 'Trash', 'clean-sweep' ), 'value' => $trash ),
					'spam'        => array( 'label' => __( 'Spam', 'clean-sweep' ), 'value' => $spam ),
					'orphan_meta' => array( 'label' => __( 'Orphan Meta', 'clean-sweep' ), 'value' => $orphan_meta ),
					'transients'  => array( 'label' => __( 'Expired Transients', 'clean-sweep' ), 'value' => $expired_ts ),
				);
				break;
		}

		if ( empty( $cards ) ) {
			return;
		}
		?>
		<div class="clean-sweep-summary">
			<?php foreach ( $cards as $key => $card ) : ?>
				<div class="clean-sweep-summary-card <?php echo esc_attr( $key ); ?>">
					<span class="clean-sweep-summary-label"><?php echo esc_html( $card['label'] ); ?></span>
					<span class="clean-sweep-summary-value"><?php echo esc_html( $card['value'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render themes tab.
     *
     * @param string $search Search query.
     */
	private static function render_themes( $search ) {
		$themes   = wp_get_themes();
		$active   = get_stylesheet();
		$filtered = array();
		foreach ( $themes as $slug => $theme ) {
			$name = $theme->get( 'Name' );
			if ( $search && false === stripos( $name, $search ) && false === stripos( $slug, $search ) ) {
				continue;
			}
			$filtered[ $slug ] = $theme;
		}

		if ( empty( $filtered ) ) {
			echo '<p>' . esc_html__( 'No themes found.', 'clean-sweep' ) . '</p>';
			return;
		}

		foreach ( $filtered as $slug => $theme ) {
			$is_active = ( $slug === $active );
			$version   = $theme->get( 'Version' );
			$size      = self::dir_size( $theme->get_stylesheet_directory() );
			?>
			<div class="clean-sweep-card <?php echo $is_active ? 'active' : 'inactive'; ?>">
				<label class="clean-sweep-card-header">
					<input type="checkbox" name="cs_items[]" value="<?php echo esc_attr( $slug ); ?>" <?php disabled( $is_active ); ?>>
					<strong><?php echo esc_html( $theme->get( 'Name' ) ); ?></strong>
					<span class="clean-sweep-badge <?php echo $is_active ? 'active' : 'inactive'; ?>"><?php echo $is_active ? esc_html__( 'Active', 'clean-sweep' ) : esc_html__( 'Inactive', 'clean-sweep' ); ?></span>
				</label>
				<div class="clean-sweep-card-body">
					<p><?php esc_html_e( 'Version:', 'clean-sweep' ); ?> <?php echo esc_html( $version ); ?></p>
					<p><?php esc_html_e( 'Size:', 'clean-sweep' ); ?> <?php echo esc_html( size_format( $size ) ); ?></p>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Render plugins tab.
     *
     * @param string $search Search query.
     */
	private static function render_plugins( $search ) {
		$plugins       = get_plugins();
		$active_plugins = wp_get_active_and_valid_plugins();
		$active_slugs   = array();
		foreach ( $active_plugins as $path ) {
			$active_slugs[ plugin_basename( $path ) ] = true;
		}

		$filtered = array();
		foreach ( $plugins as $slug => $data ) {
			$name = $data['Name'];
			if ( $search && false === stripos( $name, $search ) && false === stripos( $slug, $search ) ) {
				continue;
			}
			$filtered[ $slug ] = $data;
		}

		if ( empty( $filtered ) ) {
			echo '<p>' . esc_html__( 'No plugins found.', 'clean-sweep' ) . '</p>';
			return;
		}

		foreach ( $filtered as $slug => $data ) {
			$is_active = isset( $active_slugs[ $slug ] );
			$path      = WP_PLUGIN_DIR . '/' . $slug;
			$size      = is_dir( $path ) ? self::dir_size( $path ) : ( file_exists( $path ) ? filesize( $path ) : 0 );
			?>
			<div class="clean-sweep-card <?php echo $is_active ? 'active' : 'inactive'; ?>">
				<label class="clean-sweep-card-header">
					<input type="checkbox" name="cs_items[]" value="<?php echo esc_attr( $slug ); ?>" <?php disabled( $is_active ); ?>>
					<strong><?php echo esc_html( $data['Name'] ); ?></strong>
					<span class="clean-sweep-badge <?php echo $is_active ? 'active' : 'inactive'; ?>"><?php echo $is_active ? esc_html__( 'Active', 'clean-sweep' ) : esc_html__( 'Inactive', 'clean-sweep' ); ?></span>
				</label>
				<div class="clean-sweep-card-body">
					<p><?php esc_html_e( 'Version:', 'clean-sweep' ); ?> <?php echo esc_html( $data['Version'] ); ?></p>
					<p><?php esc_html_e( 'Size:', 'clean-sweep' ); ?> <?php echo esc_html( size_format( $size ) ); ?></p>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Render media tab.
     *
     * @param string $search Search query.
     */
	private static function render_media( $search ) {
		$args  = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => 50,
			'paged'          => isset( $_GET['cs_paged'] ) ? absint( $_GET['cs_paged'] ) : 1,
			's'              => $search,
		);
		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			echo '<p>' . esc_html__( 'No image attachments found.', 'clean-sweep' ) . '</p>';
			return;
		}

		foreach ( $query->posts as $post ) {
			$src  = wp_get_attachment_thumb_url( $post->ID );
			$size = (int) get_post_meta( $post->ID, '_wp_attachment_metadata', true )['filesize'] ?? 0;
			if ( ! $size ) {
				$file = get_attached_file( $post->ID );
				$size = file_exists( $file ) ? filesize( $file ) : 0;
			}
			?>
			<div class="clean-sweep-card media">
				<label class="clean-sweep-card-header">
					<input type="checkbox" name="cs_items[]" value="<?php echo esc_attr( $post->ID ); ?>">
					<strong><?php echo esc_html( get_the_title( $post->ID ) ); ?></strong>
				</label>
				<div class="clean-sweep-card-body">
					<?php if ( $src ) : ?>
						<img src="<?php echo esc_url( $src ); ?>" alt="" class="clean-sweep-thumb">
					<?php endif; ?>
					<p><?php esc_html_e( 'Size:', 'clean-sweep' ); ?> <?php echo esc_html( size_format( $size ) ); ?></p>
				</div>
			</div>
			<?php
		}

		$total   = $query->max_num_pages;
		$current = $args['paged'];
		if ( $total > 1 ) {
			echo '<div class="clean-sweep-pagination">';
			for ( $i = 1; $i <= $total; $i++ ) {
				$class = ( $i === $current ) ? 'button button-primary' : 'button';
				printf( '<a class="%s" href="%s">%d</a> ', esc_attr( $class ), esc_url( add_query_arg( 'cs_paged', $i ) ), intval( $i ) );
			}
			echo '</div>';
		}
	}

	/**
	 * Render database tab.
     */
	private static function render_database() {
		global $wpdb;

		$revisions    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );
		$autodrafts   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
		$trash        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" );
		$spam         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
		$orphan_meta  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );
		$expired_ts   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < " . time() );
		$all_ts       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%'" );

		$items = array(
			'revisions'   => array( 'label' => __( 'Post Revisions', 'clean-sweep' ), 'count' => $revisions ),
			'autodrafts'  => array( 'label' => __( 'Auto-Drafts', 'clean-sweep' ), 'count' => $autodrafts ),
			'trash'       => array( 'label' => __( 'Trashed Posts', 'clean-sweep' ), 'count' => $trash ),
			'spam'        => array( 'label' => __( 'Spam Comments', 'clean-sweep' ), 'count' => $spam ),
			'orphan_meta' => array( 'label' => __( 'Orphan Post Meta', 'clean-sweep' ), 'count' => $orphan_meta ),
			'transients'  => array( 'label' => __( 'Expired Transients', 'clean-sweep' ), 'count' => $expired_ts, 'all' => $all_ts ),
		);

		foreach ( $items as $key => $item ) {
			?>
			<div class="clean-sweep-card db">
				<label class="clean-sweep-card-header">
					<input type="checkbox" name="cs_items[]" value="<?php echo esc_attr( $key ); ?>">
					<strong><?php echo esc_html( $item['label'] ); ?></strong>
				</label>
				<div class="clean-sweep-card-body">
					<p><?php esc_html_e( 'Items:', 'clean-sweep' ); ?> <?php echo esc_html( number_format_i18n( $item['count'] ) ); ?></p>
					<?php if ( 'transients' === $key ) : ?>
						<p><?php esc_html_e( 'All transients:', 'clean-sweep' ); ?> <?php echo esc_html( number_format_i18n( $item['all'] ) ); ?></p>
						<p>
							<label>
								<input type="checkbox" name="cs_expired_only" value="1" checked>
								<?php esc_html_e( 'Delete expired only', 'clean-sweep' ); ?>
							</label>
						</p>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Delete themes.
     *
     * @param array $slugs Theme slugs.
     * @return array
     */
	private static function delete_themes( $slugs ) {
		if ( ! function_exists( 'delete_theme' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}

		$active = get_stylesheet();
		$ok     = 0;
		$fail   = 0;

		foreach ( $slugs as $slug ) {
			if ( ! $slug || $slug === $active ) {
				$fail++;
				continue;
			}
			$theme = wp_get_theme( $slug );
			if ( ! $theme->exists() ) {
				$fail++;
				continue;
			}
			$backup = self::backup_dir() . '/' . wp_date( 'Y-m-d' ) . '/theme-' . $slug . '.zip';
			self::zip_dir( $theme->get_stylesheet_directory(), $backup );
			delete_theme( $slug );
			$ok++;
		}

		return array(
			'success' => $ok > 0,
			'message' => sprintf( __( 'Themes deleted: %1$d, skipped: %2$d.', 'clean-sweep' ), $ok, $fail ),
		);
	}

	/**
	 * Delete plugins.
     *
     * @param array $slugs Plugin slugs.
     * @return array
     */
	private static function delete_plugins( $slugs ) {
		if ( ! function_exists( 'delete_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_plugins = wp_get_active_and_valid_plugins();
		$active_slugs   = array_map( 'plugin_basename', $active_plugins );
		$ok             = 0;
		$fail           = 0;

		foreach ( $slugs as $slug ) {
			if ( ! $slug || in_array( $slug, $active_slugs, true ) ) {
				$fail++;
				continue;
			}
			$path = WP_PLUGIN_DIR . '/' . $slug;
			if ( ! file_exists( $path ) ) {
				$fail++;
				continue;
			}
			$backup = self::backup_dir() . '/' . wp_date( 'Y-m-d' ) . '/plugin-' . basename( $slug ) . '.zip';
			self::zip_path( $path, $backup );
			delete_plugins( array( $slug ) );
			$ok++;
		}

		return array(
			'success' => $ok > 0,
			'message' => sprintf( __( 'Plugins deleted: %1$d, skipped: %2$d.', 'clean-sweep' ), $ok, $fail ),
		);
	}

	/**
	 * Delete media attachments.
     *
     * @param array $ids Attachment IDs.
     * @return array
     */
	private static function delete_media( $ids ) {
		$ok   = 0;
		$fail = 0;

		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( ! $id ) {
				$fail++;
				continue;
			}
			$files = array();
			$file  = get_attached_file( $id );
			if ( $file && file_exists( $file ) ) {
				$files[] = $file;
			}
			$meta = wp_get_attachment_metadata( $id );
			if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
				$dir = trailingslashit( dirname( $file ) );
				foreach ( $meta['sizes'] as $size ) {
					if ( ! empty( $size['file'] ) && file_exists( $dir . $size['file'] ) ) {
						$files[] = $dir . $size['file'];
					}
				}
			}

			if ( ! empty( $files ) ) {
				$backup = self::backup_dir() . '/' . wp_date( 'Y-m-d' ) . '/media-' . $id . '.zip';
				self::zip_files( $files, $backup );
			}

			if ( wp_delete_attachment( $id, true ) ) {
				$ok++;
			} else {
				$fail++;
			}
		}

		return array(
			'success' => $ok > 0,
			'message' => sprintf( __( 'Media deleted: %1$d, skipped: %2$d.', 'clean-sweep' ), $ok, $fail ),
		);
	}

	/**
	 * Cleanup database items.
     *
     * @param string $type Cleanup type.
     * @param array  $selected Selected keys.
     * @return array
     */
	private static function cleanup_database( $type, $selected ) {
		global $wpdb;

		$ok      = 0;
		$fail    = 0;
		$backup_file = self::backup_dir() . '/' . wp_date( 'Y-m-d-H-i-s' ) . '-' . $type . '.sql';

		switch ( $type ) {
			case 'revisions':
				$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'" );
				$ok  = count( $ids );
				self::backup_table_rows( $wpdb->posts, "ID IN (" . implode( ',', array_map( 'intval', $ids ) ) . ")", $backup_file );
				$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'" );
				break;
			case 'autodrafts':
				$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
				$ok  = count( $ids );
				self::backup_table_rows( $wpdb->posts, "ID IN (" . implode( ',', array_map( 'intval', $ids ) ) . ")", $backup_file );
				$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
				break;
			case 'trash':
				$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash'" );
				$ok  = count( $ids );
				self::backup_table_rows( $wpdb->posts, "ID IN (" . implode( ',', array_map( 'intval', $ids ) ) . ")", $backup_file );
				$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'" );
				break;
			case 'spam':
				$ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
				$ok  = count( $ids );
				self::backup_table_rows( $wpdb->comments, "comment_ID IN (" . implode( ',', array_map( 'intval', $ids ) ) . ")", $backup_file );
				$wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
				break;
			case 'orphan_meta':
				$ids = $wpdb->get_col( "SELECT pm.meta_id FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );
				$ok  = count( $ids );
				self::backup_table_rows( $wpdb->postmeta, "meta_id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ")", $backup_file );
				$wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );
				break;
			default:
				$fail++;
		}

		return array(
			'success' => $ok > 0,
			'message' => sprintf( __( 'Database cleanup completed: %1$d items removed. Backup saved to %2$s.', 'clean-sweep' ), $ok, esc_html( $backup_file ) ),
		);
	}

	/**
	 * Cleanup transients.
     *
     * @param bool $expired_only Whether to delete only expired transients.
     * @return array
     */
	private static function cleanup_transients( $expired_only ) {
		global $wpdb;

		$backup_file = self::backup_dir() . '/' . wp_date( 'Y-m-d-H-i-s' ) . '-transients.sql';
		$time        = time();
		$where       = "option_name LIKE '_transient_timeout_%'";
		if ( $expired_only ) {
			$where .= " AND option_value < {$time}";
		}

		$timeout_ids = $wpdb->get_col( "SELECT option_id FROM {$wpdb->options} WHERE {$where}" );
		$ok          = 0;

		if ( ! empty( $timeout_ids ) ) {
			$names = array();
			foreach ( $timeout_ids as $id ) {
				$name = $wpdb->get_var( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_id = %d", $id ) );
				if ( $name ) {
					$transient_name = str_replace( '_transient_timeout_', '', $name );
					$names[]        = "'_transient_" . esc_sql( $transient_name ) . "'";
					$names[]        = "'_transient_timeout_" . esc_sql( $transient_name ) . "'";
				}
			}
			if ( ! empty( $names ) ) {
				$in    = implode( ',', $names );
				$ok    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name IN ({$in})" );
				self::backup_table_rows( $wpdb->options, "option_name IN ({$in})", $backup_file );
				$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name IN ({$in})" );
			}
		}

		return array(
			'success' => $ok > 0,
			'message' => sprintf( __( 'Transients removed: %1$d. Backup saved to %2$s.', 'clean-sweep' ), $ok, esc_html( $backup_file ) ),
		);
	}

	/**
	 * Backup table rows to SQL file.
     *
     * @param string $table Table name.
     * @param string $where SQL where clause.
     * @param string $file  Backup file path.
     */
	private static function backup_table_rows( $table, $where, $file ) {
		global $wpdb;

		wp_mkdir_p( dirname( $file ) );
		$fp     = fopen( $file, 'a' );
		$cols   = $wpdb->get_results( "SHOW COLUMNS FROM {$table}", ARRAY_A );
		$fields = array();
		foreach ( $cols as $col ) {
			$fields[] = '`' . $col['Field'] . '`';
		}
		$field_str = implode( ', ', $fields );

		$rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where}", ARRAY_N );
		foreach ( $rows as $row ) {
			$values = array();
			foreach ( $row as $value ) {
				if ( is_null( $value ) ) {
					$values[] = 'NULL';
				} else {
					$values[] = "'" . $wpdb->_real_escape( $value ) . "'";
				}
			}
			fwrite( $fp, "INSERT INTO {$table} ({$field_str}) VALUES (" . implode( ', ', $values ) . ");\n" );
		}
		fclose( $fp );
	}

	/**
	 * Zip a directory.
     *
     * @param string $source Source path.
     * @param string $dest   Destination zip file.
     */
	private static function zip_dir( $source, $dest ) {
		wp_mkdir_p( dirname( $dest ) );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $dest, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return;
		}

		$source = realpath( $source );
		if ( false === $source ) {
			$zip->close();
			return;
		}

		$base = dirname( $source );
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
		foreach ( $iterator as $file ) {
			$file_path = $file->getRealPath();
			$relative  = str_replace( $base . '/', '', $file_path );
			if ( strpos( realpath( $file_path ), $base ) !== 0 ) {
				continue;
			}
			if ( $file->isDir() ) {
				$zip->addEmptyDir( $relative );
			} else {
				$zip->addFile( $file_path, $relative );
			}
		}
		$zip->close();
	}

	/**
	 * Zip a plugin/theme path.
     *
     * @param string $source Source path.
     * @param string $dest   Destination zip file.
     */
	private static function zip_path( $source, $dest ) {
		wp_mkdir_p( dirname( $dest ) );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $dest, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return;
		}

		$source = realpath( $source );
		if ( false === $source ) {
			$zip->close();
			return;
		}

		if ( is_file( $source ) ) {
			$zip->addFile( $source, basename( $source ) );
			$zip->close();
			return;
		}

		$base     = dirname( $source );
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
		foreach ( $iterator as $file ) {
			$file_path = $file->getRealPath();
			$relative  = str_replace( $base . '/', '', $file_path );
			if ( strpos( realpath( $file_path ), realpath( $base ) ) !== 0 ) {
				continue;
			}
			if ( $file->isDir() ) {
				$zip->addEmptyDir( $relative );
			} else {
				$zip->addFile( $file_path, $relative );
			}
		}
		$zip->close();
	}

	/**
	 * Zip array of files.
     *
     * @param array  $files Files to zip.
     * @param string $dest  Destination zip file.
     */
	private static function zip_files( $files, $dest ) {
		wp_mkdir_p( dirname( $dest ) );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $dest, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return;
		}
		foreach ( $files as $file ) {
			$real = realpath( $file );
			if ( false !== $real && strpos( $real, realpath( WP_CONTENT_DIR ) ) === 0 ) {
				$zip->addFile( $real, basename( $real ) );
			}
		}
		$zip->close();
	}

	/**
	 * Directory size.
     *
     * @param string $dir Directory path.
     * @return int
     */
	private static function dir_size( $dir ) {
		$size = 0;
		if ( ! is_dir( $dir ) ) {
			return 0;
		}
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			$size += $file->getSize();
		}
		return $size;
	}
}
