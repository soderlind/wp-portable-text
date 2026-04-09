const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const DependencyExtractionPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

// Modules we bundle ourselves (React 19 vs WP's React 18).
const BUNDLED_MODULES = new Set( [
	'react',
	'react-dom',
	'react/jsx-runtime',
	'react-dom/client',
] );

module.exports = {
	...defaultConfig,
	entry: {
		'editor/index': path.resolve( __dirname, 'src/editor/index.tsx' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
	// Replace the WP dependency extraction plugin with one that
	// does NOT externalize React — we bundle React 19 because
	// @portabletext/editor requires it and WP 7 ships React 18.
	plugins: [
		...( defaultConfig.plugins || [] ).filter(
			( plugin ) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin',
		),
		new DependencyExtractionPlugin( {
			// Return false to prevent externalization of React modules.
			requestToExternal( request ) {
				if ( BUNDLED_MODULES.has( request ) ) {
					// Return an empty array to signal "not external" without
					// cascading to defaults.
					return false;
				}
				// Return undefined to cascade to defaults for everything else.
				return undefined;
			},
			requestToHandle( request ) {
				if ( BUNDLED_MODULES.has( request ) ) {
					return false;
				}
				return undefined;
			},
		} ),
	],
};
