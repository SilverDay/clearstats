<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Plugin\System\Clearstats\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

/**
 * Injects the ClearStats tracking snippet (see docs/tracking-guide.md in the
 * ClearStats repository) into every frontend page. No cookies, no browser
 * storage — nothing this plugin does changes that; it only places the
 * <script> tag itself.
 */
final class Clearstats extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeCompileHead' => 'injectTrackingScript',
        ];
    }

    public function injectTrackingScript(): void
    {
        $app = $this->getApplication();

        // Never load the tracker in the administrator, and never break if
        // no application/document is available (e.g. CLI contexts).
        if ($app === null || !$app->isClient('site')) {
            return;
        }

        $siteId = trim((string) $this->params->get('site_id', ''));
        $scriptDomain = rtrim((string) $this->params->get('script_domain', 'https://clearstats.online'), '/');

        if ($siteId === '' || $scriptDomain === '') {
            return;
        }

        $attributes = sprintf(' data-site-id="%s"', htmlspecialchars($siteId, ENT_QUOTES, 'UTF-8'));

        // Opt-in per feature, matching track.js's own opt-in-by-attribute
        // design: installing this plugin must not silently start collecting
        // more than the site explicitly asks for.
        if ((bool) $this->params->get('track_outbound_links', 0)) {
            $attributes .= ' data-outbound-links';
        }

        if ((bool) $this->params->get('track_file_downloads', 0)) {
            $attributes .= ' data-file-downloads';
        }

        // data-404 must only ever be present while Joomla is actually
        // rendering its error page — adding it unconditionally would make
        // every normal page load count as a 404. This checks the HTTP
        // status Joomla has already set by this point in the request; if
        // your Joomla version/configuration sets it later than
        // onBeforeCompileHead, this will under-fire rather than over-fire
        // (verify in a staging environment — see the integration README).
        if ((bool) $this->params->get('track_404', 0) && http_response_code() === 404) {
            $attributes .= ' data-404';
        }

        $scriptUrl = htmlspecialchars($scriptDomain . '/js/track.js', ENT_QUOTES, 'UTF-8');

        $app->getDocument()->addCustomTag(
            sprintf('<script defer%s src="%s"></script>', $attributes, $scriptUrl),
        );
    }
}
