function convertEpochToGMT(epochTime) {
    // Create a new Date object using the epoch time (in milliseconds)
    const date = new Date(epochTime * 1000);

    // Convert the date to a GMT string
    return date.toGMTString();
}

function convertBytesToGB(bytes) {
    const GB = 1024 * 1024 * 1024; // 1 GB = 1024^3 bytes
    return (bytes / GB).toFixed(2);
}

// Tautulli Bootstrap Table Response Handler
function tautulliResponseHandler(response) {
    const data = response.data.rows;
    if (response.result == "Success") {
        data.sort((a, b) => b.last_played - a.last_played);
        const totalShows = data.length;
        const recentShows = data.filter(row => {
            if (row.length != 0) {
                const lastPlayedDate = new Date(row.last_played * 1000); // Convert epoch to date
                const ninetyDaysAgo = new Date();
                ninetyDaysAgo.setDate(ninetyDaysAgo.getDate() - 90);
                return lastPlayedDate >= ninetyDaysAgo;
            } else {
                return false;
            }
        }).length;

        $('#recentlyWatched').text(recentShows);
        $('#totalShows').text(response.data.total);
        return {
            total: response.data.total,
            rows: data
        };
    } else {
        toast("Error", "", response.message, "danger", "30000");
    }
}

// Tautulli Last Watched Date Formatter
function tautulliLastWatchedFormatter(value, row, index) {
    if (row.last_played) {
        return convertEpochToGMT(row.last_played);
    } else {
        return 'Never';
    }
}

// Bytes to GB Formatter
function sonarrSizeOnDiskFormatter(value, row, index) {
    if (row.sizeOnDisk) {
        return convertBytesToGB(row.sizeOnDisk)+' GB';
    }
}

function sonarrEpisodeProgressFormatter(value, row, index) {
    if (row.episodesDownloadedPercentage) {
        var percentage = row.episodesDownloadedPercentage.toFixed(2);
        return '<div class="progress"><div class="progress-bar bg-info" role="progressbar" style="width: '+percentage+'%" aria-valuenow="'+percentage+'" aria-valuemin="0" aria-valuemax="100">'+percentage+'%</div></div>';
    }
}

function customQueryParams(params) {
    return {
        limit: params.limit,
        offset: params.offset,
        search: params.search,
        sort: params.sort,
        order: params.order,
        filter: params.filter
    };
}

// Initate TV Shows Table
$("#tvShowsTable").bootstrapTable();