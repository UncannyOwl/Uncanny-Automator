import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const URL = '/edit.php?post_type=uo-recipe&page=uncanny-automator-dashboard';

exports.PlaywrightAutomatorDashboard = class PlaywrightAutomatorDashboard {

  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(admin, page) {
    this.admin = admin;
    this.page = page;
    this.title = page.locator('#uap-dashboard').getByText('Dashboard');
    this.button = page.locator('uo-button').getByText('Connect your site').first();
    this.knowledgebaseHeader = page.getByText('Knowledge base');
    this.appCreditsLeft = page.getByText('App credits left').first();
    this.kbList = page.locator('#uap-dashboard-learn-knowledge-base .uap-dashboard-box-content');
  }

  async goTo() {
    await this.admin.visitAdminPage( URL );
  }

  async getStarted() {
    await this.getStartedLink.first().click();
    await expect(this.gettingStartedHeader).toBeVisible();
  }

  async pageObjectModel() {
    await this.getStarted();
    await this.pomLink.click();
  }
};