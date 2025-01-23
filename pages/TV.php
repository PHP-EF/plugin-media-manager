<?php
$MediaManager = new MediaManager();
$pluginConfig = $MediaManager->config->get('Plugins','Media Manager');
if ($MediaManager->auth->checkAccess($pluginConfig['ACL-MEDIAMANAGER'] ?? "ACL-MEDIAMANAGER") == false) {
    $phpef->api->setAPIResponse('Error','Unauthorized',401);
    return false;
}
return '
    <div class="row m-1">
        <!-- TV Shows Card -->
        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-lg-0 mb-1">
            <div class="card info-card tv-shows-card">
                <div class="card-body metric-box bg-info">
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fas fa-tv mb-2"></i>&nbsp;
                            <h5 class="card-title">TV Shows: </h5>
                        </div>
                        <div class="pt-1 ps-3">
                            <h6 id="totalItems" class="metric-circle border-5">N/A</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Recently Watched Card -->
        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-lg-0 mb-1">
            <div class="card info-card tv-shows-card">
                <div class="card-body metric-box bg-success">
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fas fa-check mb-2"></i>&nbsp;
                            <h5 class="card-title">Recently Watched: </h5>
                        </div>
                        <div class="pt-1 ps-3">
                            <h6 id="recentlyWatched" class="metric-circle border-5">N/A</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Cleanup Pending Card -->
        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-lg-0 mb-1">
            <div class="card info-card tv-shows-card">
                <div class="card-body metric-box bg-warning">
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fas fa-clock mb-2"></i>&nbsp;
                            <h5 class="card-title">Cleanup Pending: </h5>
                        </div>
                        <div class="pt-1 ps-3">
                            <h6 id="cleanupPending" class="metric-circle border-5">N/A</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Space To Free Card -->
        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-lg-0 mb-1">
            <div class="card info-card tv-shows-card">
                <div class="card-body metric-box bg-danger">
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fas fa-trash mb-2"></i>&nbsp;
                            <h5 class="card-title">Space to Free: </h5>
                        </div>
                        <div class="pt-1 ps-3">
                            <h6 id="spaceToFree" class="metric-circle border-5">N/A</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row m-lg-3">
        <!-- TV Shows List -->
        <div class="table-responsive">
            <table data-url="/api/mediamanager/media/tvshows"
                data-toggle="table"
                data-search="true"
                data-filter-control="true"
                data-filter-control-visible="false"
                data-show-filter-control-switch="true"
                data-show-refresh="true"
                data-show-columns="true"
                data-sort-name="last_played"
                data-sort-order="desc"
                data-pagination="true"
                data-side-pagination="server"
                data-toolbar="#toolbar"
                data-page-size="25"
                data-query-params="customQueryParams"
                data-response-handler="tautulliResponseHandler"
                class="table table-striped" id="tvShowsTable">

                <thead>
                <tr>
                    <th data-field="state" data-checkbox="true"></th>
                    <th data-field="title" data-sortable="true" data-filter-control="input">Show Name</th>
                    <th data-field="monitored" data-sortable="true" data-filter-control="select" data-visible="false">Monitored</th>
                    <th data-field="status" data-sortable="true" data-filter-control="select">Show Status</th>
                    <th data-field="matchStatus" data-sortable="true" data-filter-control="select" data-visible="false">Match Status</th>
                    <th data-field="seasonCount" data-sortable="true" data-filter-control="input">Seasons Monitored</th>
                    <th data-field="episodeCount" data-sortable="true" data-filter-control="input" data-visible="false">Episodes Monitored</th>
                    <th data-field="episodeFileCount" data-sortable="true" data-filter-control="input" data-visible="false">Episodes Downloaded</th>
                    <th data-field="episodesDownloadedPercentage" data-sortable="true" data-formatter="sonarrEpisodeProgressFormatter" data-filter-control="input">Episodes Downloaded</th>
                    <th data-field="sizeOnDisk" data-sortable="true" data-formatter="sonarrSizeOnDiskFormatter" data-filter-control="input">Size</th>
                    <th data-field="seriesType" data-sortable="true" data-filter-control="input" data-visible="false">Series Type</th>
                    <th data-field="last_played" data-sortable="true" data-formatter="tautulliLastWatchedFormatter" data-filter-control="input">Last Watched</th>
                    <th data-field="added" data-sortable="true" data-formatter="dateFormatter" data-filter-control="input" data-visible="false">Added</th>
                    <th data-field="play_count" data-sortable="true" data-filter-control="input">Play Count</th>
                    <th data-field="library" data-sortable="true" data-filter-control="select">Library</th>
                    <th data-field="library_id" data-sortable="true" data-filter-control="select" data-visible="false">Library ID</th>
                    <th data-field="clean" data-sortable="true" data-filter-control="select" data-formatter="cleanupFormatter" >Cleanup</th>
                </tr>
                </thead>
            </table>
        </div>
    </div>
<script>
// Initate TV Shows Table
$("#tvShowsTable").bootstrapTable();
</script>
';