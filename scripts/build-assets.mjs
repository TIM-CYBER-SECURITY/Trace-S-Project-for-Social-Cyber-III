import fs from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import { createRequire } from 'node:module';
import { compile, optimize } from '@tailwindcss/node';
import { Scanner } from '@tailwindcss/oxide';
import * as esbuild from 'esbuild';

const require = createRequire(import.meta.url);
const rootDir = process.cwd();
const buildDir = path.join(rootDir, 'public', 'build');
const assetsDir = path.join(buildDir, 'assets');

function shortHash(buffer) {
    return crypto.createHash('sha256').update(buffer).digest('hex').slice(0, 8);
}

async function resolveCssPath(id, base) {
    if (id === 'tailwindcss') {
        return path.join(rootDir, 'node_modules', 'tailwindcss', 'index.css');
    }
    if (id.startsWith('.')) {
        return path.resolve(base, id);
    }
    try {
        return require.resolve(id, { paths: [base, path.join(rootDir, 'node_modules')] });
    } catch {
        const candidate = path.join(rootDir, 'node_modules', id);
        return candidate.endsWith('.css') ? candidate : `${candidate}.css`;
    }
}

async function buildCss() {
    const cssEntry = path.join(rootDir, 'resources', 'css', 'app.css');
    const source = await fs.readFile(cssEntry, 'utf8');

    const compiler = await compile(source, {
        base: path.dirname(cssEntry),
        from: cssEntry,
        shouldRewriteUrls: true,
        onDependency: () => {},
    });

    const sources = (compiler.root === 'none' ? [] : compiler.root === null ? [{ base: rootDir, pattern: '**/*', negated: false }] : [{ ...compiler.root, negated: false }])
        .concat(compiler.sources);

    const scanner = new Scanner({ sources });
    const candidates = [...scanner.scan()];
    const rawCss = compiler.build(candidates);

    const optimized = optimize(rawCss, { minify: true });

    const esResult = await esbuild.build({
        stdin: {
            contents: optimized.code,
            resolveDir: path.dirname(cssEntry),
            loader: 'css',
        },
        bundle: true,
        minify: true,
        metafile: true,
        outdir: assetsDir,
        entryNames: 'app-[hash]',
        assetNames: '[name]-[hash]',
        loader: {
            '.woff2': 'file',
            '.woff': 'file',
            '.ttf': 'file',
            '.eot': 'file',
            '.svg': 'file',
        },
        write: true,
    });

    const cssOutEntry = Object.keys(esResult.metafile.outputs).find((f) => f.endsWith('.css'));
    if (!cssOutEntry) {
        throw new Error('CSS build produced no .css output');
    }
    const relCss = path.relative(buildDir, path.resolve(rootDir, cssOutEntry)).replace(/\\/g, '/');

    return relCss;
}

async function buildJs() {
    const jsEntry = path.join(rootDir, 'resources', 'js', 'app.js');

    const esResult = await esbuild.build({
        entryPoints: [jsEntry],
        bundle: true,
        minify: true,
        format: 'esm',
        platform: 'browser',
        target: ['es2020'],
        outdir: assetsDir,
        entryNames: 'app-[hash]',
        write: false,
    });

    const jsOut = esResult.outputFiles[0];
    const fileName = `app-${shortHash(jsOut.contents)}.js`;
    await fs.writeFile(path.join(assetsDir, fileName), jsOut.contents);

    return `assets/${fileName}`;
}

async function main() {
    await fs.rm(buildDir, { recursive: true, force: true });
    await fs.mkdir(assetsDir, { recursive: true });

    const cssFile = await buildCss();
    const jsFile = await buildJs();

    const manifest = {
        'resources/css/app.css': {
            file: cssFile,
            src: 'resources/css/app.css',
            isEntry: true,
        },
        'resources/js/app.js': {
            file: jsFile,
            name: 'app',
            src: 'resources/js/app.js',
            isEntry: true,
        },
    };

    await fs.writeFile(
        path.join(buildDir, 'manifest.json'),
        JSON.stringify(manifest, null, 2) + '\n',
        'utf8'
    );

    console.log('TRACE-S frontend bundle built cleanly:');
    console.log('  CSS:', cssFile);
    console.log('  JS :', jsFile);
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
