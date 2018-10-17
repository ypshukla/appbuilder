// (C) Copyright 2015 Martin Dougiamas
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

import { NgModule } from '@angular/core';
import { CoreCronDelegate } from '@providers/cron';
import { CoreCourseProvider } from './providers/course';
import { CoreCourseHelperProvider } from './providers/helper';
import { CoreCourseFormatDelegate } from './providers/format-delegate';
import { CoreCourseModuleDelegate } from './providers/module-delegate';
import { CoreCourseOfflineProvider } from './providers/course-offline';
import { CoreCourseModulePrefetchDelegate } from './providers/module-prefetch-delegate';
import { CoreCourseOptionsDelegate } from './providers/options-delegate';
import { CoreCourseFormatDefaultHandler } from './providers/default-format';
import { CoreCourseModuleDefaultHandler } from './providers/default-module';
import { CoreCourseFormatSingleActivityModule } from './formats/singleactivity/singleactivity.module';
import { CoreCourseFormatSocialModule } from './formats/social/social.module';
import { CoreCourseFormatTopicsModule } from './formats/topics/topics.module';
import { CoreCourseFormatWeeksModule } from './formats/weeks/weeks.module';
import { CoreCourseSyncProvider } from './providers/sync';
import { CoreCourseSyncCronHandler } from './providers/sync-cron-handler';

// List of providers (without handlers).
export const CORE_COURSE_PROVIDERS: any[] = [
    CoreCourseProvider,
    CoreCourseHelperProvider,
    CoreCourseFormatDelegate,
    CoreCourseModuleDelegate,
    CoreCourseModulePrefetchDelegate,
    CoreCourseOptionsDelegate,
    CoreCourseOfflineProvider,
    CoreCourseSyncProvider
];

@NgModule({
    declarations: [],
    imports: [
        CoreCourseFormatSingleActivityModule,
        CoreCourseFormatTopicsModule,
        CoreCourseFormatWeeksModule,
        CoreCourseFormatSocialModule
    ],
    providers: [
        CoreCourseProvider,
        CoreCourseHelperProvider,
        CoreCourseFormatDelegate,
        CoreCourseModuleDelegate,
        CoreCourseModulePrefetchDelegate,
        CoreCourseOptionsDelegate,
        CoreCourseOfflineProvider,
        CoreCourseSyncProvider,
        CoreCourseFormatDefaultHandler,
        CoreCourseModuleDefaultHandler,
        CoreCourseSyncCronHandler
    ],
    exports: []
})
export class CoreCourseModule {
    constructor(cronDelegate: CoreCronDelegate, syncHandler: CoreCourseSyncCronHandler) {
        cronDelegate.register(syncHandler);
    }
}
