<?php
/**
 * Created by PhpStorm.
 * User: Hiệp Nguyễn
 * Date: 27/09/2021
 * Time: 11:06
 */


namespace Nguyenhiep\BookingLunch;


use Carbon\Carbon;
use Nesk\Puphpeteer\Puppeteer;
use Nesk\Rialto\Data\JsFunction;
use Nguyenhiep\BookingLunch\Exceptions\BookingFailedExceptions;
use Nguyenhiep\BookingLunch\Exceptions\LoginFailedException;

class User
{
    private $browser;
    private string $username;
    private string $password;
    private string $company;
    private string $possition;

    public function __construct(string $username, string $password, string $company, string $possition, bool $headless = true, string $proxy = null)
    {
        $this->username  = $username;
        $this->password  = $password;
        $this->company   = $company;
        $this->possition = $possition;
        $puppeteer       = new Puppeteer([
            'read_timeout' => 600, // 10 minutes
            'idle_timeout' => 180, // 3 minutes
        ]);

        $this->browser = $puppeteer->launch([
            'headless' => $headless,
            'args'     => [
                '--proxy-server=' . $proxy,
                '--no-sandbox',
                '--incognito'
            ],
        ]);
        $this->browser->userAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4577.82 Safari/537.36');
    }

    public function __destruct()
    {
        $this->browser->close();
    }

    /**
     * @throws LoginFailedException
     */
    public function login()
    {
        try {
            $context = $this->browser->createIncognitoBrowserContext();
            $page    = $context->newPage();
            $page->tryCatch->goto(config("lunch-booking.login_url"), ['waitUntil' => 'networkidle0']);
            $page->type("body > app-root > app-authentication > app-authentication-login > div > div > div > div.form-region > div.input-group.d-inline-block > form > nz-tabset > div > div > div > div.mb-3.email-code-username.ng-star-inserted > input", $this->username);
            $page->type("body > app-root > app-authentication > app-authentication-login > div > div > div > div.form-region > div.input-group.d-inline-block > form > nz-tabset > div > div > div > div.mb-2.password.position-relative.ng-star-inserted > input", $this->password);
            $page->click("body > app-root > app-authentication > app-authentication-login > div > div > div > div.form-region > div.input-group.d-inline-block > form > nz-tabset > div > div > div > button");
            $page->waitForNavigation(["waitUntil" => 'networkidle0']);
            return $page;
        } catch (\Exception $exception) {
            throw new LoginFailedException($exception->getMessage(), 444);
        }
    }

    /**
     * @throws BookingFailedExceptions
     */
    public function book_lunch($page): bool
    {
        try {
            $page->tryCatch->goto(config("lunch-booking.booking_url"));
            $next_day = now()->addDays(now()->dayOfWeek === Carbon::FRIDAY ? 3 : 1)->format("l, F j, Y");
            $page->waitForSelector('[aria-label="' . $next_day . '"]', ['timeout' => 60000, 'visible' => true]);

            //Add event
            $page->evaluate(JsFunction::createWithBody('document.querySelector(\'[aria-label="' . $next_day . '"]\').click();'));
            $page->evaluate(JsFunction::createWithBody('document.querySelector(\'#layoutEmbed > app-dynamic-embed > app-dynamic-layout-detail > div > layout-embed > div > layout-embed-stack:nth-child(1) > div > nz-affix > div > div > app-d-view > data-table > app-render-tree > div > div > ejs-schedule > div.e-quick-popup-wrapper.e-lib.e-popup.e-control.e-popup-open > div > div.e-popup-footer > div > button\').click();'));
            $page->waitForSelector('[title="' . $this->company . '"]', ['timeout' => 60000, 'visible' => true]);
            $page->waitForSelector('[title="' . $this->possition . '"]', ['timeout' => 60000, 'visible' => true]);
            //Submit form
            $page->evaluate(JsFunction::createWithBody('document.querySelector(\'#cdk-overlay-4 > nz-modal-container > div > div > div > app-popup-add-item > app-d-form > div > form > div.card-footer.text-xl-right.d-flex.justify-content-end.ng-star-inserted > span:nth-child(2) > button\').click();'));
            $page->waitForSelector('nz-notification > div > div', ['timeout' => 60000, 'visible' => true]);
            return true;
        } catch (\Throwable $exception) {
            throw new BookingFailedExceptions($exception->getMessage(), 444);
        }
    }
}