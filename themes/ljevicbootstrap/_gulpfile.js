// Load plugins
const autoprefixer = require("autoprefixer");
const browsersync = require("browser-sync").create();
const cp = require("child_process");
const cssnano = require("cssnano");
const del = require("del");
const eslint = require("gulp-eslint");
const gulp = require("gulp");
const imagemin = require("gulp-imagemin");
const newer = require("gulp-newer");
const plumber = require("gulp-plumber");
const postcss = require("gulp-postcss");
const rename = require("gulp-rename");
const sass = require("gulp-sass");
const webpack = require("webpack");
const webpackconfig = require("./webpack.config.js");
const webpackstream = require("webpack-stream");

// CSS task
function css() {
    return gulp
        .src("./assets/scss/**/*.scss")
        .pipe(plumber())
        .pipe(sass({ outputStyle: "expanded" }))
        .pipe(gulp.dest("./assets/css/"))
        .pipe(rename({ suffix: ".min" }))
        .pipe(postcss([autoprefixer(), cssnano()]))
        .pipe(gulp.dest("./assets/css/"))
        .pipe(browsersync.stream());
}

// Watch files
function watchFiles() {
    gulp.watch("./assets/scss/**/*", css);
    gulp.watch("./assets/img/**/*", images);
}

// Tasks
gulp.task("images", images);
gulp.task("css", css);

// build
gulp.task("build", gulp.series(clean, gulp.parallel(css, images))
);

// watch
gulp.task("watch", gulp.parallel(watchFiles, browserSync));