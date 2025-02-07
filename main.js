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




// ** Widgets ** //

// ** Queues ** //
const timeouts = {};

function homepageDownloader(type, timeout = 60000) {
    const actions = {
        jdownloader: 'getJdownloader',
        sabnzbd: 'getSabnzbd',
        nzbget: 'getNzbget',
        transmission: 'getTransmission',
        sonarr: 'getSonarrQueue',
        radarr: 'getRadarrQueue',
        qbittorrent: 'getqbittorrent',
        deluge: 'getDeluge',
        rTorrent: 'getrTorrent',
        utorrent: 'getutorrent'
    };

    const action = actions[type];
    if (!action) return;

    const lowerType = type.toLowerCase();
    queryAPI('GET', `api/mediamanager/${lowerType}/queue`).done(data => {
        try {
            const response = data.data;
            if (response !== null) {
                buildDownloaderItem(response, type);
            }
        } catch (e) {
            toast('Error', "", e, "danger");
        }
    }).fail(xhr => {
        toast('Error', "", xhr, "danger");
    });

    const timeoutTitle = `${type}-Queue`;
    if (timeouts[timeoutTitle]) {
        clearTimeout(timeouts[timeoutTitle]);
    }
    timeouts[timeoutTitle] = setTimeout(() => homepageDownloader(type, timeout), timeout);
}

function buildDownloaderCombined(source) {
    const first = $('.combinedDownloadRow').length === 0;
    const activeLi = first ? 'active show' : '';
    const activeA = first ? 'active' : '';
    let queueButton = 'QUEUE';
    let historyButton = 'HISTORY';

    const sourcesWithHistory = ['sabnzbd', 'nzbget'];
    const sourcesWithQueueOnly = ['jdownloader', 'utorrent', 'transmission', 'qbittorrent', 'deluge', 'rTorrent', 'sonarr', 'radarr'];

    const queue = sourcesWithQueueOnly.includes(source) || sourcesWithHistory.includes(source);
    const history = sourcesWithHistory.includes(source);

    if (source === 'jdownloader' || source === 'transmission' || source === 'qbittorrent' || source === 'deluge' || source === 'rTorrent' || source === 'sonarr' || source === 'radarr') {
        queueButton = 'REFRESH';
    }

    let mainMenu = `<ul class="nav customtab nav-tabs combinedMenuList" role="tablist">`;
    const addToMainMenu = `<li role="presentation" class="${activeLi}"><a onclick="homepageDownloader('${source}')" href="#combined-${source}" aria-controls="home" role="tab" data-bs-toggle="tab" aria-expanded="true" class="${activeA}"><span class=""><img src="/api/image/plugin/Media Manager/${source}/png" class="widgetTitleImage"><span class="badge bg-info downloaderCount" id="count-${source}"><i class="fa fa-spinner fa-spin"></i></span></span></a></li>`;
    let listing = '';
    let menu = `<ul class="nav customtab nav-tabs m-t-5" role="tablist">`;

    if (queue) {
        menu += `
            <li role="presentation" class="active" onclick="homepageDownloader('${source}')"><a href="#${source}-queue" aria-controls="home" role="tab" data-bs-toggle="tab" aria-expanded="true" class="active"><span class="visible-xs"><i class="ti-download"></i></span><span class="hidden-xs">${queueButton}</span></a></li>
        `;
        listing += `
            <div role="tabpanel" class="tab-pane fade active in show" id="${source}-queue">
                <div class="inbox-center table-responsive">
                    <table class="table table-hover">
                        <tbody class="${source}-queue"></tbody>
                    </table>
                </div>
                <div class="clearfix"></div>
            </div>
        `;
    }

    if (history) {
        menu += `
            <li role="presentation" class=""><a href="#${source}-history" aria-controls="profile" role="tab" data-bs-toggle="tab" aria-expanded="false"><span class="visible-xs"><i class="ti-time"></i></span> <span class="hidden-xs">${historyButton}</span></a></li>
        `;
        listing += `
            <div role="tabpanel" class="tab-pane fade" id="${source}-history">
                <div class="inbox-center table-responsive">
                    <table class="table table-hover">
                        <tbody class="${source}-history"></tbody>
                    </table>
                </div>
                <div class="clearfix"></div>
            </div>
        `;
    }

    menu += `<li class="${source}-downloader-action"></li></ul><div class="clearfix"></div>`;
    menu = (queue && history) ? menu : '';
    const listingMain = `<div role="tabpanel" class="tab-pane fade ${activeLi} in" id="combined-${source}">${menu}<div class="tab-content m-t-0 listingSingle">${listing}</div></div>`;
    mainMenu += first ? addToMainMenu + '</ul>' : '';

    if (first) {
        const header = `
            <div class="m-b-0 p-b-0 p-10 ">
                ${mainMenu}
                <div class="clearfix"></div>
            </div>
        `;
        const built = `
            <div class="row combinedDownloadRow">
                <div class="col-lg-12">
                    ${header}
                    <div class="p-0">
                        <div class="tab-content m-t-0 listingMain">${listingMain}</div>
                    </div>
                </div>
            </div>
        `;
        $('#homepageOrderdownloader').html(built);
    } else {
        $(addToMainMenu).appendTo('.combinedMenuList');
        $(listingMain).appendTo('.listingMain');
    }
}

function buildDownloaderItem(array, source, type = 'none') {
    let queue = '';
    let count = 0;
    let history = '';

    const getStatusAction = (status) => {
        const statusActions = {
            RUNNING: `<tr><td>
                        <a href="#" onclick="return false;"><span class="downloader mouse" data-source="jdownloader" data-action="pause" data-bs-target="main"><i class="fa fa-pause"></i></span></a>
                        <a href="#" onclick="return false;"><span class="downloader mouse" data-source="jdownloader" data-action="stop" data-bs-target="main"><i class="fa fa-stop"></i></span></a>
                      </td></tr>`,
            PAUSE: `<tr><td><a href="#" onclick="return false;"><span class="downloader mouse" data-source="jdownloader" data-action="resume" data-bs-target="main"><i class="fa fa-fast-forward"></i></span></a></td></tr>`,
            default: `<tr><td><a href="#" onclick="return false;"><span class="downloader mouse" data-source="jdownloader" data-action="start" data-bs-target="main"><i class="fa fa-play"></i></span></a></td></tr>`
        };
        return statusActions[status] || statusActions.default;
    };

    const buildQueueItems = (items, status) => {
        items.forEach(v => {
            count += 1;
            const speed = v.speed || 'Stopped';
            const eta = v.eta || (v.percentage === '100' ? '--' : '--');
            const enabled = v.enabled || 'Disabled';
            queue += `
            <tr>
                <td class="max-texts">${v.name}</td>
                <td>${speed}</td>
                <td class="hidden-xs" alt="${v.done}">${v.size}</td>
                <td class="hidden-xs">${eta}</td>
                <td class="text-right">
                    <div class="progress progress-lg m-b-0">
                        <div class="progress-bar progress-bar-info" style="width: ${v.percentage}%;" role="progressbar">${v.percentage}%</div>
                    </div>
                </td>
            </tr>
            `;
        });
    };

    switch (source) {
        case 'jdownloader':
            if (array.content === false) {
                queue = `<tr><td class="max-texts" lang="en">Connection Error to ${source}</td></tr>`;
                break;
            }

            if (array.content.queueItems.length === 0 && array.content.grabberItems.length === 0 && array.content.encryptedItems.length === 0 && array.content.offlineItems.length === 0) {
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
            } else {
                queue += getStatusAction(array.content.$status[0]);
                if (array.content.$status[1]) {
                    queue += `<tr><td><a href="#" onclick="return false;"><span class="downloader mouse" data-source="jdownloader" data-action="update" data-bs-target="main"><i class="fa fa-globe"></i></span></a></td></tr>`;
                }
            }

            buildQueueItems(array.content.queueItems, 'queue');
            buildQueueItems(array.content.grabberItems, 'grabber');
            buildQueueItems(array.content.encryptedItems, 'encrypted');
            buildQueueItems(array.content.offlineItems, 'offline');
            break;

        case 'sabnzbd':
            if (array.content === false) {
                queue = `<tr><td class="max-texts" lang="en">Connection Error to ${source}</td></tr>`;
                break;
            }

            const sabnzbdState = array.content.queueItems.queue.paused ? 
                `<a href="#" onclick="return false;"><span class="downloader mouse" data-source="sabnzbd" data-action="resume" data-bs-target="main"><i class="fa fa-play"></i></span></a>` : 
                `<a href="#" onclick="return false;"><span class="downloader mouse" data-source="sabnzbd" data-action="pause" data-bs-target="main"><i class="fa fa-pause"></i></span></a>`;
            $('.sabnzbd-downloader-action').html(sabnzbdState);

            if (array.content.queueItems.queue.slots.length === 0) {
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
            }

            array.content.queueItems.queue.slots.forEach(v => {
                count += 1;
                const action = v.status === "Downloading" ? 'pause' : 'resume';
                const actionIcon = v.status === "Downloading" ? 'pause' : 'play';
                queue += `
                <tr>
                    <td class="max-texts">${v.filename}</td>
                    <td class="hidden-xs sabnzbd-${cleanString(v.status)}">${v.status}</td>
                    <td class="downloader mouse" data-bs-target="${v.nzo_id}" data-source="sabnzbd" data-action="${action}"><i class="fa fa-${actionIcon}"></i></td>
                    <td class="hidden-xs"><span class="label label-info">${v.cat}</span></td>
                    <td class="hidden-xs">${v.size}</td>
                    <td class="hidden-xs" alt="${v.eta}">${v.timeleft}</td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: ${v.percentage}%;" role="progressbar">${v.percentage}%</div>
                        </div>
                    </td>
                </tr>
                `;
            });

            if (array.content.historyItems.history.slots.length === 0) {
                history = '<tr><td class="max-texts" lang="en">Nothing in history</td></tr>';
            }

            array.content.historyItems.history.slots.forEach(v => {
                history += `
                <tr>
                    <td class="max-texts">${v.name}</td>
                    <td class="hidden-xs sabnzbd-${cleanString(v.status)}">${v.status}</td>
                    <td class="hidden-xs"><span class="label label-info">${v.category}</span></td>
                    <td class="hidden-xs">${v.size}</td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: 100%;" role="progressbar">100%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
            break;

        case 'nzbget':
            if (array.content === false) {
                queue = `<tr><td class="max-texts" lang="en">Connection Error to ${source}</td></tr>`;
                break;
            }

            if (array.content.queueItems.result.length === 0) {
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
            }
            const nzbgetQueueItems = array.content.queueItems.result || [];
            const nzbgetHistoryItems = array.content.historyItems.result || [];

            if (nzbgetQueueItems.length === 0) {
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
            } else {
                nzbgetQueueItems.forEach(v => {
                    count += 1;
                    const action = v.Status === "Downloading" ? 'pause' : 'resume';
                    const actionIcon = v.Status === "Downloading" ? 'pause' : 'play';
                    const percent = Math.floor((v.FileSizeMB - v.RemainingSizeMB) * 100 / v.FileSizeMB);
                    const size = v.FileSizeMB * 1000000;
                    v.Category = v.Category !== '' ? v.Category : 'Not Set';
                    queue += `
                    <tr>
                        <td class="max-texts">${v.NZBName}</td>
                        <td class="hidden-xs nzbget-${cleanString(v.Status)}">${v.Status}</td>
                        <!--<td class="downloader mouse" data-bs-target="${v.NZBID}" data-source="sabnzbd" data-action="${action}"><i class="fa fa-${actionIcon}"></i></td>-->
                        <td class="hidden-xs"><span class="label label-info">${v.Category}</span></td>
                        <td class="hidden-xs">${humanFileSize(size, true)}</td>
                        <td class="text-right">
                            <div class="progress progress-lg m-b-0">
                                <div class="progress-bar progress-bar-info" style="width: ${percent}%;" role="progressbar">${percent}%</div>
                            </div>
                        </td>
                    </tr>
                    `;
                });
            }

            if (nzbgetHistoryItems.length === 0) {
                history = '<tr><td class="max-texts" lang="en">Nothing in history</td></tr>';
            } else {
                nzbgetHistoryItems.forEach(v => {
                    v.Category = v.Category !== '' ? v.Category : 'Not Set';
                    const size = v.FileSizeMB * 1000000;
                    history += `
                    <tr>
                        <td class="max-texts">${v.NZBName}</td>
                        <td class="hidden-xs nzbget-${cleanString(v.Status)}">${v.Status}</td>
                        <td class="hidden-xs"><span class="label label-info">${v.Category}</span></td>
                        <td class="hidden-xs">${humanFileSize(size, true)}</td>
                        <td class="text-right">
                            <div class="progress progress-lg m-b-0">
                                <div class="progress-bar progress-bar-info" style="width: 100%;" role="progressbar">100%</div>
                            </div>
                        </td>
                    </tr>
                    `;
                });
            }
            break;

        case 'transmission':
            if (array.content === false) {
                queue = `<tr><td class="max-texts" lang="en">Connection Error to ${source}</td></tr>`;
                break;
            }

            if (array.content.queueItems.length === 0) {
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
            }

            array.content.queueItems.forEach(v => {
                count += 1;
                const statusMap = {
                    7: 'No Peers',
                    6: 'Seeding',
                    5: 'Seeding Queued',
                    4: 'Downloading',
                    3: 'Queued',
                    2: 'Checking Files',
                    1: 'File Check Queued',
                    0: 'Complete'
                };
                const status = statusMap[v.status] || 'Complete';
                const percent = Math.floor(v.percentDone * 100);
                v.Category = v.Category !== '' ? v.Category : 'Not Set';
                queue += `
                <tr>
                    <td class="max-texts">${v.name}</td>
                    <td class="hidden-xs transmission-${cleanString(status)}">${status}</td>
                    <td class="hidden-xs">${v.downloadDir}</td>
                    <td class="hidden-xs">${humanFileSize(v.totalSize, true)}</td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: ${percent}%;" role="progressbar">${percent}%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
            break;

        case 'rTorrent':
            if (array.content === false) {
                queue = `<tr><td class="max-texts" lang="en">Connection Error to ${source}</td></tr>`;
                break;
            }

            if (array.content.queueItems.length === 0) {
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
            }

            array.content.queueItems.forEach(v => {
                count += 1;
                const percent = Math.floor((v.downloaded / v.size) * 100);
                const size = v.size !== -1 ? humanFileSize(v.size, false) : "?";
                const upload = v.seed !== '' ? humanFileSize(v.seed, true) : "0 B";
                const download = v.leech !== '' ? humanFileSize(v.leech, true) : "0 B";
                const upTotal = v.upTotal !== '' ? humanFileSize(v.upTotal, false) : "0 B";
                const downTotal = v.downTotal !== '' ? humanFileSize(v.downTotal, false) : "0 B";
                let date = new Date(0);
                date.setUTCSeconds(v.date);
                date = moment(date).format('LLL');
                queue += `
                <tr>
                    <td class="max-texts"><span class="tooltip-info" data-bs-toggle="tooltip" data-placement="right" title="" data-original-title="${date}">${v.name}</span></td>
                    <td class="hidden-xs rtorrent-${cleanString(v.status)}">${v.status}</td>
                    <td class="hidden-xs"><span class="tooltip-info" data-bs-toggle="tooltip" data-placement="right" title="" data-original-title="${downTotal}"><i class="fa fa-download"></i>&nbsp;${download}</span></td>
                    <td class="hidden-xs"><span class="tooltip-info" data-bs-toggle="tooltip" data-placement="right" title="" data-original-title="${upTotal}"><i class="fa fa-upload"></i>&nbsp;${upload}</span></td>
                    <td class="hidden-xs">${size}</td>
                    <td class="hidden-xs"><span class="label label-info">${v.label}</span></td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: ${percent}%;" role="progressbar">${percent}%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
            break;

        case 'utorrent':
            if (array.content === false) {
                queue = `<tr><td class="max-texts" lang="en">Connection Error to ${source}</td></tr>`;
                break;
            }

            const utorrentQueueItems = array.content.queueItems || [];
        
            if (utorrentQueueItems.length === 0) {
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
                break;
            }
        
            utorrentQueueItems.forEach(v => {
                count += 1;
                const upload = v.upSpeed !== '' ? humanFileSize(v.upSpeed, false) : "0 B";
                const download = v.downSpeed !== '' ? humanFileSize(v.downSpeed, false) : "0 B";
                const size = v.Size !== '' ? humanFileSize(v.Size, false) : "0 B";
                queue += `
                <tr>
                    <td class="max-texts"><span class="tooltip-info" data-bs-toggle="tooltip" data-placement="right" title="">${v.Name}</span></td>
                    <td class="hidden-xs utorrent-${cleanString(v.Status)}">${v.Status}</td>
                    <td class="hidden-xs"><span class="label label-info">${v.Labels}</span></td>
                    <td class="hidden-xs"><span class="tooltip-info" data-bs-toggle="tooltip" data-placement="right" title="" data-original-title="${download}"><i class="fa fa-download"></i>&nbsp;${download}</span></td>
                    <td class="hidden-xs"><span class="tooltip-info" data-bs-toggle="tooltip" data-placement="right" title="" data-original-title="${upload}"><i class="fa fa-upload"></i>&nbsp;${upload}</span></td>
                    <td class="hidden-xs">${size}</td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: ${v.Percent};" role="progressbar">${v.Percent}</div>
                        </div>
                    </td>
                </tr>
                `;
            });
            break;

        case 'sonarr':
            if (array.content === false) {
                queue = `<tr><td class="max-texts" lang="en">Connection Error to ${source}</td></tr>`;
                break;
            }
        
            const sonarrQueueItems = array.content.queueItems || [];
            const sonarrQueueRecords = array.content.queueItems.records || [];
        
            if (sonarrQueueItems.length === 0 && sonarrQueueRecords.length === 0) {
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
                break;
            }
        
            const sonarrQueueSet = sonarrQueueRecords.length ? sonarrQueueRecords : sonarrQueueItems;
            sonarrQueueSet.forEach(v => {
                count += 1;
                const percent = Math.floor(((v.size - v.sizeleft) / v.size) * 100) || 0;
                const size = v.size !== -1 ? humanFileSize(v.size, false) : "?";
                v.name = v.series ? v.series.title : v.title;
                queue += `
                <tr>
                    <td class="">${v.name}</td>
                    <td class="">S${pad(v.episode.seasonNumber, 2)}E${pad(v.episode.episodeNumber, 2)}</td>
                    <td class="max-texts">${v.episode.title}</td>
                    <td class="hidden-xs sonarr-${cleanString(v.status)}">${v.status}</td>
                    <td class="hidden-xs">${size}</td>
                    <td class="hidden-xs"><span class="label label-info">${v.protocol}</span></td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: ${percent}%;" role="progressbar">${percent}%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
            break;

        case 'radarr':
            if (array.content === false) {
                queue = `<tr><td class="max-texts" lang="en">Connection Error to ${source}</td></tr>`;
                break;
            }

            const radarrQueueItems = array.content.queueItems || [];
            const radarrQueueRecords = array.content.queueItems.records || [];
        
            if (radarrQueueItems.length === 0 && radarrQueueRecords.length === 0) {
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
                break;
            }
        
            const radarrQueueSet = radarrQueueRecords.length ? radarrQueueRecords : radarrQueueItems;
            radarrQueueSet.forEach(v => {
                count += 1;
                const percent = Math.floor(((v.size - v.sizeleft) / v.size) * 100) || 0;
                const size = v.size !== -1 ? humanFileSize(v.size, false) : "?";
                v.name = v.movie ? v.movie.title : v.title;
                queue += `
                <tr>
                    <td class="max-texts">${v.name}</td>
                    <td class="hidden-xs sonarr-${cleanString(v.status)}">${v.status}</td>
                    <td class="hidden-xs">${size}</td>
                    <td class="hidden-xs"><span class="label label-info">${v.protocol}</span></td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: ${percent}%;" role="progressbar">${percent}%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
            break;

        case 'qbittorrent':
            if (array.content === false) {
                queue = `<tr><td class="max-texts" lang="en">Connection Error to ${source}</td></tr>`;
                break;
            }

            if (array.content.queueItems.length === 0) {
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
            }

            array.content.queueItems.forEach(v => {
                count += 1;
                const statusMap = {
                    stalledDL: 'No Peers',
                    metaDL: 'Getting Metadata',
                    uploading: 'Seeding',
                    queuedUP: 'Seeding Queued',
                    downloading: 'Downloading',
                    queuedDL: 'Queued',
                    checkingDL: 'Checking Files',
                    checkingUP: 'Checking Files',
                    pausedDL: 'Paused',
                    pausedUP: 'Complete'
                };
                const status = statusMap[v.state] || 'Complete';
                const percent = Math.floor(v.progress * 100);
                const size = v.total_size !== -1 ? humanFileSize(v.total_size, true) : "?";
                queue += `
                <tr>
                    <td class="max-texts">${v.name}</td>
                    <td class="hidden-xs qbit-${cleanString(status)}">${status}</td>
                    <td class="hidden-xs">${v.save_path}</td>
                    <td class="hidden-xs">${size}</td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: ${percent}%;" role="progressbar">${percent}%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
            break;

        case 'deluge':
            if (array.content === false) {
                queue = `<tr><td class="max-texts" lang="en">Connection Error to ${source}</td></tr>`;
                break;
            }

            if (array.content.queueItems.length === 0) {
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
            }

            array.content.queueItems.forEach(v => {
                count += 1;
                const percent = Math.floor(v.progress);
                const size = v.total_size !== -1 ? humanFileSize(v.total_size, true) : "?";
                const upload = v.upload_payload_rate !== -1 ? humanFileSize(v.upload_payload_rate, true) : "?";
                const download = v.download_payload_rate !== -1 ? humanFileSize(v.download_payload_rate, true) : "?";
                queue += `
                <tr>
                    <td class="max-texts">${v.name}`;
                if (v.tracker_status !== "") {
                    queue += `<i class="fa fa-caret-down ml-2" style="cursor:pointer" onclick="$(this).toggleClass('fa-caret-down');$(this).toggleClass('fa-caret-up');$('#status-${v.hash}').toggleClass('d-none');" aria-hidden="true"></i><br /><div class="well mb-0 mt-2 p-3 d-none" id="status-${v.hash}">${v.tracker_status}</div>`;
                }
                queue += `</td>
                    <td class="hidden-xs deluge-${cleanString(v.state)}">${v.state}</td>
                    <td class="hidden-xs">${size}</td>
                    <td class="hidden-xs"><i class="fa fa-download"></i>&nbsp;${download}</td>
                    <td class="hidden-xs"><i class="fa fa-upload"></i>&nbsp;${upload}</td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: ${percent}%;" role="progressbar">${percent}%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
            break;

        default:
            return false;
    }
    if (queue !== '') {
        $(`.${source}-queue`).html(queue);
    }
    if (history !== '') {
        $(`.${source}-history`).html(history);
    }
    $(`#count-${source}`).html(count);
}

function tvActionFormatter(value, row, index) {
  var buttons = [];
  buttons.push(`<a class="cleanup" title="Cleanup Now"><i class="fa-solid fa-hand-sparkles"></i></a>&nbsp;`);
  return buttons.join("");
}