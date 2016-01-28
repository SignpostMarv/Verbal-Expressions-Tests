module.exports = function gruntConfig(grunt) {
    // because manually loading is tedious
    module.require('load-grunt-tasks')(grunt);
    grunt.initConfig({
        watch: {
            Gruntfile: {
                files: [
                    'Gruntfile.js',
                ],
                tasks: [
                    'eslint:Gruntfile',
                ],
            },
        },
        eslint: {
            options: {
                configFile: '.eslintrc',
            },
            Gruntfile: [
                'Gruntfile.js',
            ],
        },
        jsonschema: {
            tests: {
                files: {
                    'tests.schema.json': 'tests/*.json',
                },
            },
        },
    });

    grunt.loadNpmTasks('grunt-jsonschema-ajv');

    grunt.registerTask('default', [
        'eslint',
        'jsonschema',
    ]);
};
