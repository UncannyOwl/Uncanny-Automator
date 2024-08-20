# Pre-requisites

1. Docker app installed https://docs.docker.com/get-docker/.

2. Node, npm, npx commands should work.

3. (Optional) VS Code Playwright extension.


# Setup

1. Copy the `./tests/e2e/.env-sample` into `./tests/e2e/.env`. Make sure the port you set is available.

2. Run `npm run e2e:install` to install the browsers and libraries. You only need to do it once per machine.


# Running the tests

1. Run `npm run e2e:start` to spawn a new test WP instance in docker. Note that you need to run this method whenever you open VS code to properly mount the files into the sandbox.

2. Press play in the Playwright UI or run `npm run e2e`.

3. Check the report in the `./tests/e2e/.artifacts` folder.


# Recording tests using the VS code extension

1. Make sure the tests run well before the changes.

2. Prepare a file for the new test and place the cursor where you want to record.

3. Click `Record at cursor` in the Playwright extension.

4. A new incognito browser window will open.

5. We don't need to record the login sequence, so pause the recording by clicking the red circle in the top control bar.

6. Log in using the credentials from your .env file

7. Click the `Record at cursor` in the VS code extension again.

8. Now your browser should record any actions you perform.

9. Perform the actions you wanted to record.

10. You can switch to assertions in the top control bar.

11. Close the browser.

12. Clean up the test, try to re-run it.


Make sure that the test doesn't rely on any other tests. 
Each test should be able to pass from a fresh wp install with Automator activated.

# Test results

The test results will be available in the .artifacts folder. 
Open the .artifacts/test-report/index.html file in any browser to see the results of all tests.
Failed tests should include a cideo recording of retries.

# Clean up

1. Run `npm run e2e:clean`

# Troubleshooting

1. Run `npm run e2e:clean`
2. Double-check the .env file
3. Run `npm run e2e:install`