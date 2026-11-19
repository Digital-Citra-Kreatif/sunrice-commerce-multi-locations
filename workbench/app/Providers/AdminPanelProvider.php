<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Sunrice\Admin\Panel;
use Sunrice\Admin\PanelProvider;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Commerce\Admin\StaffUserResource;
use Sunrice\Commerce\CommercePlugin;
use Sunrice\CommerceMultiLocations\MultiLocationsPlugin;

/** The panel of the tests: the CMS, the store, and the locations after it. */
final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->path('admin')->userResource(StaffUserResource::class)->plugin(CmsPlugin::make())->plugin(CommercePlugin::make())->plugin(MultiLocationsPlugin::make());
    }
}
