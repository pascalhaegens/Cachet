<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CachetHQ\Cachet\Foundation\Providers;

use CachetHQ\Cachet\Models\Setting as SettingModel;
use CachetHQ\Cachet\Settings\Cache;
use CachetHQ\Cachet\Settings\Repository;
use Exception;
use Illuminate\Support\ServiceProvider;

/**
 * This is the config service provider class.
 *
 * @author James Brooks <james@alt-three.com>
 * @author Graham Campbell <graham@alt-three.com>
 * @author Joe Cohen <joe@alt-three.com>
 */
class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $env = $this->app->environment();
        $repo = $app->make(Repository::class);
        $cache = $app->make(Cache::class);
        $loaded = $cache->load();

        $this->app->terminating(function () use ($env, $repo, $cache, $loaded) {
            if ($repo->stale() || $loaded === false) {
                $cache->store($env, $repo->all());
            }
        });

        try {
            // Get the default settings.
            $defaultSettings = $this->app->config->get('setting');

            // Get the configured settings.
            $appSettings = $loaded === false ? $repo->all() : $loaded;

            // Merge the settings
            $settings = array_merge($defaultSettings, $appSettings);

            $this->app->config->set('setting', $settings);
        } catch (Exception $e) {
            //
        }

        if ($appDomain = $this->app->config->get('setting.app_domain')) {
            $this->app->config->set('app.url', $appDomain);
        }

        if ($appLocale = $this->app->config->get('setting.app.locale')) {
            $this->app->config->set('app.locale', $appLocale);
            $this->app->translator->setLocale($appLocale);
        }

        if ($appTimezone = $this->app->config->get('setting.app_timezone')) {
            $this->app->config->set('cachet.timezone', $appTimezone);
        }

        $allowedOrigins = $this->app->config->get('cors.defaults.allowedOrigins');

        if ($allowedDomains = $this->app->config->get('setting.allowed_domains')) {
            $domains = explode(',', $allowedDomains);
            foreach ($domains as $domain) {
                $allowedOrigins[] = $domain;
            }
        } else {
            $allowedOrigins[] = $this->app->config->get('app.url');
        }

        $this->app->config->set('cors.paths.api/v1/*.allowedOrigins', $allowedOrigins);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Cache::class, function ($app) {
            return new Cache($app->filesystem, $app->bootstrapPath().'/cachet');
        });

        $this->app->singleton(Repository::class, function () {
            return new Repository(new SettingModel());
        });
    }
}
