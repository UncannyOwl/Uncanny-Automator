console.log('Running global-setup.js');
/**
 * External dependencies
 */
import { request } from '@playwright/test';

/**
 * WordPress dependencies
 */
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 *
 * @param {import('@playwright/test').FullConfig} config
 * @returns {Promise<void>}
 */
async function globalSetup( config ) {
	const { storageState, baseURL } = config.projects[ 0 ].use;
	const storageStatePath =
		typeof storageState === 'string' ? storageState : undefined;

	const requestContext = await request.newContext( {
		baseURL,
	} );

	const requestUtils = new RequestUtils( requestContext, {
		user:{
			username: process.env.WP_USERNAME,
			password: process.env.WP_PASSWORD,
		},
		storageStatePath,
	} );

	// Authenticate and save the storageState to disk.
	await requestUtils.setupRest();

	// Reset the test environment before running the tests.
	await requestUtils.activateTheme( 'twentytwentyfour' );
	await requestUtils.activatePlugin( 'uncanny-automator' );
	await requestUtils.deleteAllPosts();

	// For some reason, the method below causes an error only at Buddy sandbox.
	//await requestUtils.deleteAllBlocks();

	await requestUtils.resetPreferences();

	await requestContext.dispose();
}

export default globalSetup;