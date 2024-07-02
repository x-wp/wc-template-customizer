<?php //phpcs:disable Squiz.Commenting.FunctionComment.MissingParamName, Squiz.Commenting.FunctionComment.MissingParamTag, Universal.Operators.DisallowShortTernary.Found
/**
 * Customizer_Admin class file.
 *
 * @package eXtended WooCommerce
 * @subpackage Template Customizer
 */

namespace XWC\Template;

/**
 * Customizer base class.
 */
abstract class Customizer_Base {
    /**
     * Whether the class has been initialized
     *
     * @var bool
     */
    protected static bool $init;

    /**
     * Path define tokens array
     *
     * @var array<string, string>
     */
    protected static array $path_def;

    /**
     * Basedirs array
     *
     * @var array<string, string>
     */
    protected static array $basedirs;

    /**
     * Path tokens array
     *
     * @var array<string, string>
     */
    protected static array $tokens;

    /**
     * Template filenames array
     *
     * @var array<string, array{
     *   id: string,
     *   path: string,
     *   lock: bool,
     *   base: string
     * }>
     */
    protected static array $file_def;

    /**
     * Template files array
     *
     * @var array<string, string>
     */
    protected static array $templates;

    /**
     * Locked templates array
     *
     * @var array<string>
     */
	protected static array $locked;

    /**
     * Admin object
     *
     * @var Customizer_Admin
     */
    protected static ?Customizer_Admin $admin;

    /**
     * Class constructor
     */
    public function __construct() {
        \add_filter( 'xwc_path_tokens', array( $this, 'custom_path_tokens' ), 100, 1 );
        \add_filter( 'xwc_template_files', array( $this, 'custom_template_files' ), 100, 1 );

        static::$init ??= $this->init();
    }

    /**
     * Initializes the customizer framework
     *
     * @return true
     */
    protected function init(): bool {
        \add_action( 'before_woocommerce_init', array( $this, 'run_customizer' ), 100000, 0 );
        \add_filter( 'woocommerce_get_path_define_tokens', array( $this, 'modify_path_tokens' ), 99, 1 );
        \add_filter( 'woocommerce_locate_template', array( $this, 'modify_template_path' ), 99, 2 );

        return true;
    }

    /**
     * Modify the path define tokens.
     *
     * @param  array<string, array{
     *   key: string,
     *   dir: string
     * }|string> $tokens Existing path define tokens.
     * @return array<string, array{
     *   key: string,
     *   dir: string
     * }|string>
     */
    abstract public function custom_path_tokens( array $tokens ): array;

    /**
     * Modify the template files.
     *
     * @param  array<string, array<string, bool>|array<int, string>> $files Existing template files.
     * @return array<string, array<string, bool>|array<int, string>>
     */
    abstract public function custom_template_files( array $files ): array;

    /**
     * Run the customizer.
     *
     * @return void
     */
    public function run_customizer(): void {
        static::$path_def ??= $this->define_paths();
        static::$basedirs ??= $this->define_basedirs();
        static::$tokens   ??= $this->define_tokens();

        static::$file_def  ??= $this->define_files();
        static::$templates ??= $this->define_templates();
        static::$locked    ??= $this->define_locked();

        static::$admin ??= $this->define_admin();
    }

    /**
     * Define the path tokens.
     *
     * @return array
     */
    final protected function define_paths(): array {
        /**
         * Filters the path tokens.
         *
         * @param  array<string, array> $paths The path tokens.
         * @return array<string, array>        The modified path tokens.
         *
         * @since 1.0.0
         */
        $paths  = \apply_filters( 'xwc_path_tokens', array() );
        $parsed = array();

        foreach ( $paths as $key => $def ) {
            $parsed[ $key ] = $this->parse_path( $key, $def );
        }

        return $paths;
    }

    /**
     * Parse the path tokens.
     *
     * @param  string       $idk  The path token key.
     * @param  array|string $path The path token definition.
     * @return array
     */
    final protected function parse_path( string $idk, array|string $path ): array {
        $path = (array) $path;
        $dir  = $path['dir'] ?? $path[0];
        $key  = $path['key'] ?? \strtoupper( \str_replace( '-', '_', $idk ) );

        return \compact( 'key', 'dir' );
    }

    /**
     * Define the basedirs.
     *
     * @return array
     */
    final protected function define_basedirs(): array {
        return \wp_list_pluck( static::$path_def, 'dir' );
    }

    /**
     * Define the path tokens.
     *
     * @return array
     */
    final protected function define_tokens(): array {
        return \wp_list_pluck( static::$path_def, 'dir', 'key' );
    }

    /**
     * Define the template files.
     *
     * @return array
     */
    final protected function define_files(): array {
        /**
         * Filters the template files.
         *
         * @param  array<string, array> $files The template files.
         * @return array<string, array>        The modified template files.
         *
         * @since 1.0.0
         */
        $files  = \apply_filters( 'xwc_template_files', array() );
        $parsed = array();

        foreach ( $files as $id => $group ) {
            $base   = \trailingslashit( static::$basedirs[ $id ] );
            $parsed = \array_merge( $parsed, $this->parse_files( $id, $base, $group ) );
        }

        return $parsed;
    }

    /**
     * Parse the template files.
     *
     * @param  string $id   The template group ID.
     * @param  string $base The base directory.
     * @param  array  $tmpl The template group definition.
     */
    final protected function parse_files( string $id, string $base, array $tmpl ): array {
        $grouped = array();

        foreach ( $tmpl as $foi => $lock ) {
            $file = \is_int( $foi ) ? $lock : $foi;
            $lock = \is_int( $foi ) ? false : $lock;
            $path = $base . $file;

            $grouped[ $file ] = \compact( 'id', 'path', 'lock', 'base' );
        }

        return $grouped;
    }

    /**
     * Define the templates.
     *
     * @return array
     */
    final protected function define_templates(): array {
        return \wp_list_pluck( static::$file_def, 'path' );
    }

    /**
     * Define the locked templates.
     *
     * @return array
     */
    final protected function define_locked(): array {
        return \wp_list_pluck( static::$file_def, 'lock' );
    }

    /**
     * Define the admin object.
     *
     * @return Customizer_Admin|null
     */
    protected function define_admin(): ?Customizer_Admin {
        return \is_admin() ? new Customizer_Admin( static::$file_def ) : null;
    }

    /**
     * Adds custom path define tokens to the existing WooCommerce tokens.
     *
     * @param  array $tokens Existing path define tokens.
     * @return array         Modified array of tokens.
     */
    public function modify_path_tokens( array $tokens ): array {
        return \array_merge( $tokens, static::$tokens );
    }

    /**
     * Locate a template and return the path for inclusion.
     *
     * This is the load order:
     *
     * yourtheme/$template_path/$template_name
     * yourtheme/$template_name
     * yourplugin/$template_path/$template_name
     *
     * @param  string $path Full template path.
     * @param  string $name Template name.
     * @return string       Modified template path.
     */
    public function modify_template_path( string $path, string $name ): string {
		if ( ! isset( static::$templates[ $name ] ) ) {
			return $path;
		}

        if ( ! static::$locked[ $name ] ) {
            $path = \locate_template( array( $name, \WC()->template_path() . $name ) );
        }

        return $path ?: static::$templates[ $name ];
    }
}
