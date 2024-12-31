<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">TV Shows Cleanup Status</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary btn-sm" onclick="refreshTVShows()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="info-box bg-info">
                                <span class="info-box-icon"><i class="fas fa-tv"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Shows</span>
                                    <span class="info-box-number" id="totalShows">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-success">
                                <span class="info-box-icon"><i class="fas fa-check"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Recently Watched</span>
                                    <span class="info-box-number" id="recentlyWatched">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-warning">
                                <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Cleanup Pending</span>
                                    <span class="info-box-number" id="cleanupPending">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-danger">
                                <span class="info-box-icon"><i class="fas fa-trash"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Space to Free</span>
                                    <span class="info-box-number" id="spaceToFree">0 GB</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Log -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Activity Log</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="activityTable">
                                    <thead>
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>Show Name</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="activityLog">
                                        <!-- Activity logs will be inserted here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TV Shows List -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">TV Shows</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="showsTable">
                                    <thead>
                                        <tr>
                                            <th>Show Name</th>
                                            <th>Episodes</th>
                                            <th>Size</th>
                                            <th>Last Watched</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="showsList">
                                        <!-- TV shows will be inserted here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cleanup Confirmation Modal -->
<div class="modal fade" id="cleanupModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Cleanup</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="cleanupDetails">
                    <!-- Cleanup details will be inserted here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmCleanup()">Confirm Cleanup</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentCleanupPath = null;
let activityLog = [];

function formatSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatDate(timestamp) {
    if (!timestamp) return 'Never';
    const date = new Date(timestamp * 1000);
    return date.toLocaleString();
}

function addToActivityLog(activity) {
    activityLog.unshift(activity);
    updateActivityLog();
    updateStats();
}

function updateActivityLog() {
    const tbody = document.getElementById('activityLog');
    tbody.innerHTML = activityLog.map(activity => `
        <tr>
            <td>${formatDate(activity.timestamp)}</td>
            <td>${activity.showName}</td>
            <td>${activity.action}</td>
            <td>${activity.details}</td>
            <td>
                <span class="badge badge-${activity.status === 'success' ? 'success' : 'danger'}">
                    ${activity.status}
                </span>
            </td>
        </tr>
    `).join('');
}

function updateStats() {
    const shows = JSON.parse(localStorage.getItem('tvShows') || '[]');
    const recentCutoff = Math.floor(Date.now() / 1000) - (plextvcleaner.tautulliMonths * 30 * 24 * 60 * 60);
    
    document.getElementById('totalShows').textContent = shows.length;
    document.getElementById('recentlyWatched').textContent = shows.filter(show => 
        show.lastWatched && show.lastWatched > recentCutoff
    ).length;
    document.getElementById('cleanupPending').textContent = shows.filter(show => 
        !show.lastWatched || show.lastWatched <= recentCutoff
    ).length;

    const totalSpace = shows.reduce((acc, show) => acc + show.size, 0);
    document.getElementById('spaceToFree').textContent = formatSize(totalSpace);
}

function refreshTVShows() {
    queryAPI("GET","/api/plugin/plextvcleaner/shows").done(function(data) {
        if (data["result"] == "Success") {
            localStorage.setItem('tvShows', JSON.stringify(data.data));
            updateShowsList(data.data);
            updateStats();
        } else if (data["result"] == "Error") {
            toast(data["result"],"",data["message"],"danger");
        } else {
            toast("API Error","","Failed to get TV Shows","danger","30000");
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        toast(textStatus,"","Failed to get TV Shows","danger","30000");
    })
}

function updateShowsList(shows) {
    const tbody = document.getElementById('showsList');
    tbody.innerHTML = shows.map(show => {
        const lastWatched = formatDate(show.lastWatched);
        const isRecent = show.lastWatched && 
            show.lastWatched > Math.floor(Date.now() / 1000) - (plextvcleaner.tautulliMonths * 30 * 24 * 60 * 60);

        return `
            <tr>
                <td>${show.name}</td>
                <td>${show.episodeCount}</td>
                <td>${formatSize(show.size)}</td>
                <td>${lastWatched}</td>
                <td>
                    <span class="badge badge-${isRecent ? 'success' : 'warning'}">
                        ${isRecent ? 'Recent' : 'Cleanup Pending'}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="analyzeShow('${show.path}')">
                        Analyze
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="showCleanupModal('${show.path}')">
                        Cleanup
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function analyzeShow(path) {
    fetch(`/api/plugin/plextvcleaner/cleanup/${encodeURIComponent(path)}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ dryRun: true })
    })
    .then(response => response.json())
    .then(result => {
        const show = JSON.parse(localStorage.getItem('tvShows'))
            .find(s => s.path === path);
        
        addToActivityLog({
            timestamp: Math.floor(Date.now() / 1000),
            showName: show.name,
            action: 'Analyze',
            details: `${result.filesToDelete.length} files (${formatSize(result.totalSize)})`,
            status: 'success'
        });
    });
}

function showCleanupModal(path) {
    currentCleanupPath = path;
    const show = JSON.parse(localStorage.getItem('tvShows'))
        .find(s => s.path === path);

    fetch(`/api/plugin/plextvcleaner/cleanup/${encodeURIComponent(path)}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ dryRun: true })
    })
    .then(response => response.json())
    .then(result => {
        document.getElementById('cleanupDetails').innerHTML = `
            <h6>Show: ${show.name}</h6>
            <p>The following cleanup will be performed:</p>
            <ul>
                <li>Files to delete: ${result.filesToDelete.length}</li>
                <li>Space to free: ${formatSize(result.totalSize)}</li>
            </ul>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                This action cannot be undone. Please confirm to proceed.
            </div>
        `;
        $('#cleanupModal').modal('show');
    });
}

function confirmCleanup() {
    if (!currentCleanupPath) return;

    const show = JSON.parse(localStorage.getItem('tvShows'))
        .find(s => s.path === currentCleanupPath);

    fetch(`/api/plugin/plextvcleaner/cleanup/${encodeURIComponent(currentCleanupPath)}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ dryRun: false })
    })
    .then(response => response.json())
    .then(result => {
        $('#cleanupModal').modal('hide');
        
        addToActivityLog({
            timestamp: Math.floor(Date.now() / 1000),
            showName: show.name,
            action: 'Cleanup',
            details: `Deleted ${result.filesToDelete.length} files (${formatSize(result.totalSize)})`,
            status: 'success'
        });

        refreshTVShows();
    })
    .catch(error => {
        addToActivityLog({
            timestamp: Math.floor(Date.now() / 1000),
            showName: show.name,
            action: 'Cleanup',
            details: `Error: ${error.message}`,
            status: 'error'
        });
    });
}

// Initial load
document.addEventListener('DOMContentLoaded', function() {
    refreshTVShows();
});
</script>
