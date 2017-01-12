module.exports = function(grunt) {
	grunt.initConfig({
		bower: {
			install: {
				options: {
					copy: false
				}
			}
		},

		bower_concat: {
			all: {
				dest: {
					js: '_build/js/chatbro.js',
					css: '_build/css/chatbro.css'
				},

				mainFiles: {
					bootstrap: ['dist/js/bootstrap.js', 'dist/css/bootstrap.css'],
					'font-awesome': ['css/font-awesome.css']
				}
			}
		},

		uglify: {
			all: {
				files: {
					'js/chatbro.min.js': ['_build/js/chatbro.js', 'src/js/chatbro.admin.js']
				}
			}
		},

		cssmin: {
			all: {
				files: {
					'css/chatbro.min.css': ['_build/css/chatbro.css', 'src/css/*']
				}
			}
		},

		pkg: grunt.file.readJSON('package.json'),
		po2mo: {
		    files: {
		        src: [ 'languages/*.po',
		        	   '!node_modules/**' ],
		        expand: true,
		    },
		},

		pot: {
			options:{
				text_domain: 'chatbro-plugin', //Your text domain. Produces my-text-domain.pot
				dest: 'languages/', //directory to place the pot file
				keywords: [ //WordPress localisation functions
					'__:1',
					'_e:1',
					'_x:1,2c',
					'esc_html__:1',
					'esc_html_e:1',
					'esc_html_x:1,2c',
					'esc_attr__:1',
					'esc_attr_e:1',
					'esc_attr_x:1,2c',
					'_ex:1,2c',
					'_n:1,2',
					'_nx:1,2,4c',
					'_n_noop:1,2',
					'_nx_noop:1,2,3c'
				],
			},
			files:{
				src:  [ '**/*.php',
						'!node_modules/**' ], //Parse all php files
				expand: true,
			}
		},

		checktextdomain: {
			options:{
				text_domain: 'chatbro-plugin',
				correct_domain: true, //Will correct missing/variable domains
				keywords: [ //WordPress localisation functions
				    '__:1,2d',
				    '_e:1,2d',
				    '_x:1,2c,3d',
				    'esc_html__:1,2d',
				    'esc_html_e:1,2d',
				    'esc_html_x:1,2c,3d',
				    'esc_attr__:1,2d',
				    'esc_attr_e:1,2d',
				    'esc_attr_x:1,2c,3d',
				    '_ex:1,2c,3d',
				    '_n:1,2,4d',
				    '_nx:1,2,4c,5d',
				    '_n_noop:1,2,3d',
				    '_nx_noop:1,2,3c,4d'
				],
			},
			files: {
			src:  [ '**/*.php',
					'!node_modules/**' ], //All php files
			expand: true,
			},
		},

		svn_fetch: {
			options: {
				repository: 'https://plugins.svn.wordpress.org/chatbro/',
				path: './',
				svnOptions: {
					username: 'yozeg',
					password: 'W*ojrKFw@73V'
				}
			},
			svn: {
				map: { 'svn': 'trunk' }
			}
		},

		sync: {
			main: {
				files: [
					{ src: ['**/*.php', '**/*.js', '**/*.css', '**/*.mo', '!Gruntfile.js', '!node_modules/**', '!svn/**'],  dest: 'svn/' }
				],
				verbose: true,
				pretend: true,
				failOnError: true,
				updateAndDelete: true,
				compareUsing: "md5"
			},
		},

		clean: ['_build', 'bower_components', 'js', 'css', 'fonts'],

		copy: {
			fonts: {
				expand: true,
				src: ['bower_components/font-awesome/fonts/*', 'bower_components/bootstrap/fonts/*'],
				dest: 'fonts/',
				filter: 'isFile',
				flatten: true
			}
		}
	});

	grunt.loadNpmTasks('grunt-bower-task');
	grunt.loadNpmTasks('grunt-bower-concat');
	grunt.loadNpmTasks('grunt-po2mo');
	grunt.loadNpmTasks('grunt-pot');
	grunt.loadNpmTasks('grunt-checktextdomain');
	grunt.loadNpmTasks('grunt-svn-fetch');
	grunt.loadNpmTasks('grunt-sync');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-contrib-cssmin');
	grunt.loadNpmTasks('grunt-contrib-clean');
	grunt.loadNpmTasks('grunt-contrib-copy');

	grunt.registerTask('default', ['bower', 'bower_concat', 'uglify', 'cssmin', 'copy:fonts']);
	grunt.registerTask('compact', ["cssmin", "uglify"]);
}
