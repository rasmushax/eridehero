const fs = require('fs');
const path = require('path');
const postcss = require('postcss');
const postcssImport = require('postcss-import');
const autoprefixer = require('autoprefixer');
const cssnano = require('cssnano');

const CSS_DIR = path.join(__dirname, 'assets/css');
const BUNDLES_DIR = path.join(CSS_DIR, 'bundles');
const DIST_DIR = path.join(CSS_DIR, 'dist');

const plugins = [
    postcssImport(),
    autoprefixer(),
    cssnano({ preset: ['default', { discardComments: { removeAll: true } }] }),
];

async function buildFile(inputPath, outputPath) {
    const css = fs.readFileSync(inputPath, 'utf8');
    const result = await postcss(plugins).process(css, {
        from: inputPath,
        to: outputPath,
    });
    fs.writeFileSync(outputPath, result.css);
    const sizeKB = (result.css.length / 1024).toFixed(1);
    console.log(`  ${path.basename(outputPath)} (${sizeKB} KB)`);
}

async function main() {
    if (!fs.existsSync(DIST_DIR)) {
        fs.mkdirSync(DIST_DIR, { recursive: true });
    }

    console.log('Building CSS bundles...');

    const entries = fs.readdirSync(BUNDLES_DIR).filter(f => f.endsWith('.css'));
    for (const entry of entries) {
        const name = path.basename(entry, '.css');
        await buildFile(
            path.join(BUNDLES_DIR, entry),
            path.join(DIST_DIR, `${name}.min.css`)
        );
    }

    console.log('Building legacy bundle...');
    await buildFile(
        path.join(CSS_DIR, 'style.css'),
        path.join(DIST_DIR, 'style.min.css')
    );

    console.log('Done.');
}

main().catch(err => {
    console.error(err);
    process.exit(1);
});
