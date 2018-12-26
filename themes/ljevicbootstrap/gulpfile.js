var gulp = require('gulp');

// Mes Plugins
var sass = require('gulp-sass');

// Task
gulp.task('sass', function() {
    gulp.src('./assets/scss/**/*.scss')
        .pipe(sass())
        .pipe(gulp.dest('./assets/css'))
});

gulp.task('default', function () {
    gulp.watch('./assets/scss/**/*.scss', ['sass']);
});