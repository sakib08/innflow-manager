#!/usr/bin/env node
/**
 * Build production assets and create a distributable plugin ZIP.
 *
 * Usage:
 *   npm run plugin-zip
 *   node scripts/package-plugin.mjs
 *   node scripts/package-plugin.mjs --skip-build
 */
import { spawnSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );
const root = path.resolve( __dirname, '..' );
const pluginSlug = 'innflow-manager';
const skipBuild = process.argv.includes( '--skip-build' );

function readVersion() {
	const pkg = JSON.parse( fs.readFileSync( path.join( root, 'package.json' ), 'utf8' ) );
	const main = fs.readFileSync( path.join( root, 'innflow-manager.php' ), 'utf8' );
	const match = main.match( /^\s*\*\s*Version:\s*([^\s]+)/m );
	const phpVersion = match ? match[1].trim() : null;
	if ( phpVersion && phpVersion !== pkg.version ) {
		console.warn( `Warning: package.json version (${pkg.version}) differs from plugin header (${phpVersion}). Using plugin header.` );
	}
	return phpVersion || pkg.version || '0.0.0';
}

function run( command, args, options = {} ) {
	const result = spawnSync( command, args, {
		cwd: root,
		stdio: 'inherit',
		shell: false,
		...options,
	} );
	if ( result.status !== 0 ) {
		process.exit( result.status || 1 );
	}
}

function ensureZipAvailable() {
	const check = spawnSync( 'zip', [ '-v' ], { stdio: 'ignore' } );
	if ( check.error || check.status !== 0 ) {
		console.error( 'The `zip` command is required. Install zip and try again.' );
		process.exit( 1 );
	}
}

const version = readVersion();
const outDir = path.join( root, 'dist' );
const zipName = `${pluginSlug}-${version}.zip`;
const zipPath = path.join( outDir, zipName );
const stagingRoot = path.join( outDir, '_staging' );
const stagingPlugin = path.join( stagingRoot, pluginSlug );

console.log( `Packaging ${pluginSlug} v${version}…` );

if ( ! skipBuild ) {
	console.log( 'Building assets…' );
	run( 'npm', [ 'run', 'build' ] );
}

const requiredAssets = [
	'assets/dist/admin.js',
	'assets/dist/admin.css',
	'assets/dist/admin.asset.php',
	'assets/dist/frontend.js',
	'assets/dist/frontend.css',
	'assets/dist/frontend.asset.php',
];

for ( const rel of requiredAssets ) {
	if ( ! fs.existsSync( path.join( root, rel ) ) ) {
		console.error( `Missing built asset: ${rel}. Run npm run build first.` );
		process.exit( 1 );
	}
}

ensureZipAvailable();

fs.rmSync( stagingRoot, { recursive: true, force: true } );
fs.mkdirSync( stagingPlugin, { recursive: true } );
fs.mkdirSync( outDir, { recursive: true } );

const includePaths = [
	'innflow-manager.php',
	'hotel-booking.php',
	'readme.txt',
	'README.md',
	'includes',
	'assets/dist',
];

for ( const rel of includePaths ) {
	const src = path.join( root, rel );
	if ( ! fs.existsSync( src ) ) {
		continue;
	}
	const dest = path.join( stagingPlugin, rel );
	fs.mkdirSync( path.dirname( dest ), { recursive: true } );
	fs.cpSync( src, dest, { recursive: true } );
}

if ( fs.existsSync( zipPath ) ) {
	fs.unlinkSync( zipPath );
}

run( 'zip', [ '-r', '-q', zipPath, pluginSlug ], { cwd: stagingRoot } );
fs.rmSync( stagingRoot, { recursive: true, force: true } );

const sizeKb = Math.round( fs.statSync( zipPath ).size / 1024 );
console.log( `Created ${path.relative( root, zipPath )} (${sizeKb} KB)` );
console.log( 'Install by uploading this ZIP in WordPress → Plugins → Add New → Upload Plugin.' );
