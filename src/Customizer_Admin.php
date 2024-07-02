<?php //phpcs:disable Universal.Operators.DisallowShortTernary.Found
/**
 * Customizer_Admin class file.
 *
 * @package eXtended WooCommerce
 * @subpackage Template Customizer
 */

namespace XWC\Template;

/**
 * Customizer admin class.
 */
class Customizer_Admin {
    /**
     * Template files array
     *
     * @var array<string, array{
     *   id: string,
     *   path: string,
     *   lock: bool,
     *   base: string
     * }>
     */
    private array $files;

    /**
     * Constructor.
     *
     * @param array $files The files to check.
     */
    public function __construct( array $files ) {
        $this->files = $files;

        \add_filter(
            'woocommerce_rest_prepare_system_status',
            array( $this, 'modify_status_response' ),
            99,
            2,
        );
    }

    /**
     * Modify the status response.
     *
     * @param  \WP_REST_Response $res    The response object.
     * @param  array             $status The status array.
     * @return \WP_REST_Response
     */
    public function modify_status_response( \WP_REST_Response $res, array $status ): \WP_REST_Response {
        $overrides        = $status['theme']['overrides'] ?? array();
        $custom_files_all = $this->files;
        $woocom_files_all = \WC_Admin_Status::scan_template_files( \WC()->plugin_path() . '/templates/' );

        $custom_files_unlock = \wp_list_filter( $custom_files_all, array( 'lock' => false ) );
        $custom_files_locked = \wp_list_filter( $custom_files_all, array( 'lock' => true ) );
        $common_files_locked = \xwp_array_slice_assoc( $custom_files_locked, ...$woocom_files_all );

        $woo_dir = \trailingslashit( \WC()->plugin_path() . '/templates' );

        $overrides = $this->clear_overrides( $overrides, \array_keys( $common_files_locked ) );
        $overrides = \array_merge( $overrides, $this->check_versions( $custom_files_unlock ) );
        $overrides = \array_merge( $overrides, $this->check_versions( $custom_files_locked, $woo_dir ) );

        $status['theme']['overrides'] = $overrides;

        $res->set_data( $status );

        return $res;
    }

    /**
     * Clear the overrides.
     *
     * @param  array $overrides The overrides to clear.
     * @param  array $common    The common files.
     * @return array
     */
    protected function clear_overrides( array $overrides, array $common ): array {
        $filtered = array();
        $common   = \implode( '|', \array_map( 'preg_quote', $common ) );

        if ( ! $common ) {
            return $overrides;
        }

        foreach ( $overrides as $data ) {
            if ( \preg_match( "`$common`", $data['file'] ) ) {
                continue;
            }

            $filtered[] = $data;
        }

        return $filtered;
    }

    /**
     * Check the versions of the files.
     *
     * @param  array       $files     The files to check.
     * @param  string|null $core_path The core path.
     * @return array
     */
    protected function check_versions( array $files, ?string $core_path = null ): array {
        $overrides = array();

        foreach ( $files as $file => $args ) {
            [ $ver, $core, $path ] = $this->get_version( $file, $args, $core_path );

            $overrides[] = array(
                'core_version' => $core ?: $ver,
                'file'         => \sprintf(
                    '%s/woocommerce/%s',
                    $path,
                    \str_replace( array( $args['base'], $core_path ), '', $args['path'] ),
                ),
                'version'      => $ver ?: $core,
            );
        }

        return $overrides;
    }

    /**
     * Get a version for a set of files.
     *
     * @param  string $file      The file to check.
     * @param  array  $args      The file arguments.
     * @param  string $core_path The core path.
     * @return array
     */
    protected function get_version( string $file, array $args, ?string $core_path ): array {
        $flip = false;

        if ( ! $core_path ) {
            $core_path = \trailingslashit( \get_stylesheet_directory() . '/woocommerce' );

            $flip = true;
        }

        $ver = array(
            \WC_Admin_Status::get_file_version( $args['path'] ),
            \WC_Admin_Status::get_file_version( $core_path . $file ),
        );
        $ver = $flip ? \array_reverse( $ver ) : $ver;

        $ver[] = '' !== $ver && ! $core_path
                ? \basename( \get_stylesheet_directory() )
                : $args['id'];

        return $ver;}
}
