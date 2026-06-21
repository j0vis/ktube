import { defineConfig } from 'vitest/config';

export default defineConfig( {
	test: {
		environment: 'jsdom',
		include: [ 'tests/js/**/*.test.js' ],
		setupFiles: [ 'tests/js/setup.js' ],
		// Expose describe / test / expect / vi as globals so test files don't
		// need to import them explicitly. Combined with explicitly-imported
		// custom modules (buildCss, contrast, etc.) in each test.
		globals: true,
		// jsdom default URL is `about:blank`; cookies with the `Secure` flag
		// (and SameSite=Lax interactions) require HTTPS. Set to a stable
		// https://localhost/ so age-gate's persistence paths write a real
		// cookie that subsequent reads can see.
		environmentOptions: {
			jsdom: {
				url: 'https://localhost/',
			},
		},
	},
} );
