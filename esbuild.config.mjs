import * as esbuild from 'esbuild';
import fs from 'fs';
import path from 'path';

const outDir = path.resolve( 'assets/dist' );
fs.mkdirSync( outDir, { recursive: true } );

const watch = process.argv.includes( '--watch' );
const minify = ! watch;

const entries = [
	{ in: 'src/admin/main.jsx', out: 'admin' },
	{ in: 'src/frontend/main.jsx', out: 'frontend' },
];

function writeAssetPhp( out ) {
	fs.writeFileSync(
		path.join( outDir, `${out}.asset.php` ),
		`<?php\nreturn array(\n\t'dependencies' => array(),\n\t'version' => '${Date.now()}',\n);\n`
	);
}

const contexts = await Promise.all(
	entries.map( async ( { in: entry, out } ) => {
		const ctx = await esbuild.context( {
			entryPoints: [ entry ],
			bundle: true,
			outfile: path.join( outDir, `${out}.js` ),
			format: 'iife',
			platform: 'browser',
			target: [ 'es2018' ],
			jsx: 'automatic',
			loader: {
				'.js': 'jsx',
				'.jsx': 'jsx',
				'.css': 'empty',
			},
			define: {
				'process.env.NODE_ENV': watch ? '"development"' : '"production"',
			},
			minify,
			plugins: [
				{
					name: 'asset-php',
					setup( build ) {
						build.onEnd( ( result ) => {
							if ( result.errors.length ) {
								return;
							}
							writeAssetPhp( out );
						} );
					},
				},
			],
		} );

		if ( watch ) {
			await ctx.watch();
			return ctx;
		}

		await ctx.rebuild();
		await ctx.dispose();
		return null;
	} )
);

if ( watch ) {
	console.log( 'Watching JS (admin + frontend)…' );
	process.on( 'SIGINT', async () => {
		await Promise.all( contexts.filter( Boolean ).map( ( ctx ) => ctx.dispose() ) );
		process.exit( 0 );
	} );
} else {
	console.log( 'Built admin.js and frontend.js' );
}
