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
        const totalItems = data.length;
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
        $('#totalItems').text(response.data.total);
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
        return '<div class="progress"><div class="progress-bar bg-default" role="progressbar" style="width: '+percentage+'%" aria-valuenow="'+percentage+'" aria-valuemin="0" aria-valuemax="100">'+percentage+'%  ('+row.episodeFileCount+'/'+row.episodeCount+')</div></div>';
    }
}

function radarrDownloadStatusFormatter(value, row, index) {
    if (row.hasFile) {
        return '<span class="badge bg-success">Available</span></h1>';
    } else {
        return '<span class="badge bg-secondary">Unavailable</span></h1>';
    }
}

function cleanupFormatter(value, row, index) {
    if (row.clean) {
        return '<span class="badge bg-warning">Cleanup Pending</span></h1>';
    } else {
        return '<span class="badge bg-secondary">No Cleanup</span></h1>';
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

// Initate Movies Table
$("#moviesTable").bootstrapTable();








// ** PLEX AUTH ** //
var plex_oauth_window = null;
var plex_oauth_loader = '<style>' +
    '.login-loader-container {' +
    'font-family: "Open Sans", Arial, sans-serif;' +
    'position: absolute;' +
    'top: 0;' +
    'right: 0;' +
    'bottom: 0;' +
    'left: 0;' +
    '}' +
    '.login-loader-message {' +
    'color: #282A2D;' +
    'text-align: center;' +
    'position: absolute;' +
    'left: 50%;' +
    'top: 25%;' +
    'transform: translate(-50%, -50%);' +
    '}' +
    '.login-loader {' +
    'border: 5px solid #ccc;' +
    '-webkit-animation: spin 1s linear infinite;' +
    'animation: spin 1s linear infinite;' +
    'border-top: 5px solid #282A2D;' +
    'border-radius: 50%;' +
    'width: 50px;' +
    'height: 50px;' +
    'position: relative;' +
    'left: calc(50% - 25px);' +
    '}' +
    '@keyframes spin {' +
    '0% { transform: rotate(0deg); }' +
    '100% { transform: rotate(360deg); }' +
    '}' +
    '</style>' +
    '<div class="login-loader-container">' +
    '<div class="login-loader-message">' +
    '<div class="login-loader"></div>' +
    '<br>' +
    'Redirecting to the login page...' +
    '</div>' +
    '</div>';
function closePlexOAuthWindow() {
    if (plex_oauth_window) {
        plex_oauth_window.close();
    }
}
getPlexOAuthPin = function () {
    var x_plex_headers = getPlexHeaders();
    var deferred = $.Deferred();
    $.ajax({
        url: 'https://plex.tv/api/v2/pins?strong=true',
        type: 'POST',
        headers: x_plex_headers,
        success: function(data) {
            deferred.resolve({pin: data.id, code: data.code});
        },
        error: function() {
            closePlexOAuthWindow();
            deferred.reject();
        }
    });
    return deferred;
};
var polling = null;
function PlexOAuth(success, error, pre, id = null) {
    if (typeof pre === "function") {
        pre()
    }
    closePlexOAuthWindow();
    plex_oauth_window = newPopup('', 'Plex-OAuth', 600, 700);
    $(plex_oauth_window.document.body).html(plex_oauth_loader);
    getPlexOAuthPin().then(function (data) {
        var x_plex_headers = getPlexHeaders();
        const pin = data.pin;
        const code = data.code;
        var oauth_params = {
            'clientID': x_plex_headers['X-Plex-Client-Identifier'],
            'context[device][product]': x_plex_headers['X-Plex-Product'],
            'context[device][version]': x_plex_headers['X-Plex-Version'],
            // 'context[device][platform]': x_plex_headers['X-Plex-Platform'],
            // 'context[device][platformVersion]': x_plex_headers['X-Plex-Platform-Version'],
            // 'context[device][device]': x_plex_headers['X-Plex-Device'],
            // 'context[device][deviceName]': x_plex_headers['X-Plex-Device-Name'],
            'context[device][model]': x_plex_headers['X-Plex-Model'],
            'context[device][screenResolution]': x_plex_headers['X-Plex-Device-Screen-Resolution'],
            'context[device][layout]': 'desktop',
            'code': code
        };
        plex_oauth_window.location = 'https://app.plex.tv/auth/#!?' + encodeData(oauth_params);
        polling = pin;
        (function poll() {
            $.ajax({
                url: 'https://plex.tv/api/v2/pins/' + pin,
                type: 'GET',
                headers: x_plex_headers,
                success: function (data) {
                    if (data.authToken){
                        closePlexOAuthWindow();
                        if (typeof success === "function") {
                            success('plex',data.authToken, id)
                        }
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    if (textStatus !== "timeout") {
                        closePlexOAuthWindow();
                        if (typeof error === "function") {
                            error()
                        }
                    }
                },
                complete: function () {
                    if (!plex_oauth_window.closed && polling === pin){
                        setTimeout(function() {poll()}, 1000);
                    }
                },
                timeout: 10000
            });
        })();
    }, function () {
        closePlexOAuthWindow();
        if (typeof error === "function") {
            error()
        }
    });
}
function openOAuth(provider){
	// will actually fix this later
	closePlexOAuthWindow();
	plex_oauth_window = newPopup('', 'OAuth', 600, 700);
	$(plex_oauth_window.document.body).html(plex_oauth_loader);
	plex_oauth_window.location = 'api/v2/oauth/trakt';
}
function encodeData(data) {
    return Object.keys(data).map(function(key) {
        return [key, data[key]].map(encodeURIComponent).join("=");
    }).join("&");
}
function oAuthSuccess(type,token, id = null){
    switch(type) {
        case 'plex':
            if(id){
		        $(id).val(token);
		        $(id).change();
                toast('PlexAuth','','Successfully grabbed Plex Authentication Token','success',30000)
	        } else {
                queryAPI('POST','/api/mediamanager/plex/oauth',{"token": token}).done(function(data) {
                    if (data['result'] == 'Success') {
                        window.location.replace(data['data']['location']);
                    } else if (data['result'] == 'Error') {
                        toast(data['result'],'',data['message'],'danger',30000)
                    }
                })
            }
            break;
        default:
            break;
    }
}
function oAuthError(){
    toast('Error','','Error Connecting to oAuth Provider','error','10000');
}
function oAuthStart(type){
    switch(type){
        case 'plex':
            PlexOAuth(oAuthSuccess,oAuthError);
            break;
        default:
            break;
    }
}
function refreshPlexServers(selector = null) {
    queryAPI('GET', '/api/mediamanager/plex/servers?owned').done(function(response) {
        const selectElement = document.querySelector(selector);
        if (selectElement) {
            // Clear existing options
            selectElement.innerHTML = '<option selected="" value="">None</option>';

            if (response.result == 'Success') {
                // Add new options from the response data
                response.data.forEach(server => {
                    const option = document.createElement('option');
                    option.value = server.machineIdentifier;
                    option.textContent = server.name;
                    selectElement.appendChild(option);
                });
                toast('Success','','Retrieved list of Plex Servers','success');
            } else {
                toast(response.result,'',response.message,'danger',30000);
            }
        }
    }).fail(function(xhr) {
        toast('Error', '', xhr, 'danger', 30000);
    });
}

$('.plexOAuth').on('click', function() {
    oAuthStart('plex');
});