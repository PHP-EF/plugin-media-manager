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




// ** Widgets ** //

// ** Queues ** //
var timeouts = {};
function homepageDownloader(type, timeout){
	var timeout = (typeof timeout !== 'undefined') ? timeout : 60000;
	switch (type) {
        case 'jdownloader':
            var action = 'getJdownloader';
            break;
		case 'sabnzbd':
			var action = 'getSabnzbd';
			break;
		case 'nzbget':
			var action = 'getNzbget';
			break;
		case 'transmission':
			var action = 'getTransmission';
			break;
		case 'sonarr':
			var action = 'getSonarrQueue';
			break;
		case 'radarr':
			var action = 'getRadarrQueue';
			break;
		case 'qBittorrent':
			var action = 'getqBittorrent';
			break;
		case 'deluge':
			var action = 'getDeluge';
			break;
	        case 'rTorrent':
			var action = 'getrTorrent';
			break;
                case 'utorrent':
                        var action = 'getutorrent';
                        break;
		default:

	}
	let lowerType = type.toLowerCase();
	queryAPI('GET','api/mediamanager/'+lowerType+'/queue').done(function(data) {
        try {
            let response = data.data;
	        if(response !== null){
		        buildDownloaderItem(response, type);
	        }
        }catch(e) {
            toast('Error',"",e,"danger");
        }
	}).fail(function(xhr) {
		toast('Error',"",xhr,"danger");
	});
	let timeoutTitle = type+'-Queue';
	if(typeof timeouts[timeoutTitle] !== 'undefined'){ clearTimeout(timeouts[timeoutTitle]); }
	timeouts[timeoutTitle] = setTimeout(function(){ homepageDownloader(type,timeout); }, timeout);
	delete timeout;
}
function buildDownloader(source){
    var queueButton = 'QUEUE';
    var historyButton = 'HISTORY';
    switch (source) {
        case 'jdownloader':
            var queue = true;
            var history = false;
            queueButton = 'REFRESH';
            break;
        case 'sabnzbd':
        case 'nzbget':
            var queue = true;
            var history = true;
            break;
        case 'transmission':
        case 'qBittorrent':
        case 'deluge':
	case 'utorrent':
            var queue = true;
            break;
        case 'rTorrent':
	    case 'sonarr':
	    case 'radarr':
            var queue = true;
            var history = false;
            queueButton = 'REFRESH';
            break;
        default:
            var queue = false;
            var history = false;

    }
	var menu = `<ul class="nav customtab nav-tabs pull-right" role="tablist">`;
	var listing = '';
	var state = '';
	var active = '';
	var headerAlt = '';
	var header = '';
	if(queue){
		menu += `
			<li role="presentation" class="active" onclick="homepageDownloader('`+source+`')"><a href="#`+source+`-queue" aria-controls="home" role="tab" data-bs-toggle="tab" aria-expanded="true"><span class="visible-xs"><i class="ti-download"></i></span><span class="hidden-xs">`+queueButton+`</span></a></li>
			`;
		listing += `
		<div role="tabpanel" class="tab-pane fade active in" id="`+source+`-queue">
			<div class="inbox-center table-responsive">
				<table class="table table-hover">
					<tbody class="`+source+`-queue"></tbody>
				</table>
			</div>
			<div class="clearfix"></div>
		</div>
		`;
	}
	if(history){
		menu += `
		<li role="presentation" class=""><a href="#`+source+`-history" aria-controls="profile" role="tab" data-bs-toggle="tab" aria-expanded="false"><span class="visible-xs"><i class="ti-time"></i></span> <span class="hidden-xs">`+historyButton+`</span></a></li>
		`;
		listing += `
		<div role="tabpanel" class="tab-pane fade" id="`+source+`-history">
			<div class="inbox-center table-responsive">
				<table class="table table-hover">
					<tbody class="`+source+`-history"></tbody>
				</table>
			</div>
			<div class="clearfix"></div>
		</div>
		`;
	}
	menu += '</ul>';
    var header = `
    <div class="m-b-0 p-b-0 p-t-10 ">
        <h2 class="text-white m-0 pull-left text-uppercase"><img class="widgetTitleImage `+active+`" src="/api/image/plugin/Media Manager/`+source+`/png">  &nbsp; `+state+`</h2>
        `+menu+`
        <div class="clearfix"></div>
    </div>
    `;
	var built = `
	<div class="row">
		`+headerAlt+`
		<div class="col-lg-12">
	        `+header+`
	        <div class="p-0">
	            <div class="tab-content m-t-0">`+listing+`</div>
	        </div>
		</div>
	</div>
	`;
    return built;
}
function buildDownloaderCombined(source){
    var first = ($('.combinedDownloadRow').length == 0) ? true : false;
    var active = (first) ? 'active show' : '';
    var queueButton = 'QUEUE';
    var historyButton = 'HISTORY';
    switch (source) {
        case 'jdownloader':
            var queue = true;
            var history = false;
            queueButton = 'REFRESH';
            break;
        case 'sabnzbd':
        case 'nzbget':
            var queue = true;
            var history = true;
            break;
        case 'utorrent':
            var queue = true;
            break;
        case 'transmission':
        case 'qBittorrent':
        case 'deluge':
        case 'rTorrent':
	    case 'sonarr':
	    case 'radarr':
            var queue = true;
            var history = false;
            queueButton = 'REFRESH';
            break;
        default:
            var queue = false;
            var history = false;

    }
    var mainMenu = `<ul class="nav customtab nav-tabs combinedMenuList" role="tablist">`;
    var addToMainMenu = `<li role="presentation" class="`+active+`"><a onclick="homepageDownloader('`+source+`')" href="#combined-`+source+`" aria-controls="home" role="tab" data-bs-toggle="tab" aria-expanded="true"><span class=""><img src="/api/image/plugin/Media Manager/`+source+`/png" class="widgetTitleImage"><span class="badge bg-info downloaderCount" id="count-`+source+`"><i class="fa fa-spinner fa-spin"></i></span></span></a></li>`;
    var listing = '';
    var headerAlt = '';
    var header = '';
    var menu = `<ul class="nav customtab nav-tabs m-t-5" role="tablist">`;
    if(queue){
        menu += `
			<li role="presentation" class="active" onclick="homepageDownloader('`+source+`')"><a href="#`+source+`-queue" aria-controls="home" role="tab" data-bs-toggle="tab" aria-expanded="true"><span class="visible-xs"><i class="ti-download"></i></span><span class="hidden-xs">`+queueButton+`</span></a></li>
			`;
        listing += `
		<div role="tabpanel" class="tab-pane fade active in show" id="`+source+`-queue">
			<div class="inbox-center table-responsive">
				<table class="table table-hover">
					<tbody class="`+source+`-queue"></tbody>
				</table>
			</div>
			<div class="clearfix"></div>
		</div>
		`;
    }
    if(history){
        menu += `
		<li role="presentation" class=""><a href="#`+source+`-history" aria-controls="profile" role="tab" data-bs-toggle="tab" aria-expanded="false"><span class="visible-xs"><i class="ti-time"></i></span> <span class="hidden-xs">`+historyButton+`</span></a></li>
		`;
        listing += `
		<div role="tabpanel" class="tab-pane fade" id="`+source+`-history">
			<div class="inbox-center table-responsive">
				<table class="table table-hover">
					<tbody class="`+source+`-history"></tbody>
				</table>
			</div>
			<div class="clearfix"></div>
		</div>
		`;
    }
    menu += '<li class="'+source+'-downloader-action"></li></ul><div class="clearfix"></div>';
    menu = ((queue) && (history)) ? menu : '';
    var listingMain = '<div role="tabpanel" class="tab-pane fade '+active+' in" id="combined-'+source+'">'+menu+'<div class="tab-content m-t-0 listingSingle">'+listing+'</div></div>';
    mainMenu += (first) ? addToMainMenu + '</ul>' : '';
    if(first){
        var header = `
        <div class="m-b-0 p-b-0 p-10 ">
            `+mainMenu+`
            <div class="clearfix"></div>
        </div>
        `;
        var built = `
        <div class="row combinedDownloadRow">
            `+headerAlt+`
            <div class="col-lg-12">
                `+header+`
                <div class="p-0">
                    <div class="tab-content m-t-0 listingMain">`+listingMain+`</div>
                </div>
            </div>
        </div>
        `;
        $('#homepageOrderdownloader').html(built);
    }else{
        $(addToMainMenu).appendTo('.combinedMenuList');
        $(listingMain).appendTo('.listingMain');
    }
}

function buildDownloaderItem(array, source, type='none'){
    var queue = '';
    var count = 0;
    var history = '';
	switch (source) {
        case 'jdownloader':
            if(array.content === false){
                queue = '<tr><td class="max-texts" lang="en">Connection Error to ' + source + '</td></tr>';
                break;
            }

            if(array.content.queueItems.length == 0 && array.content.grabberItems.length == 0 && array.content.encryptedItems.length == 0 && array.content.offlineItems.length == 0){
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
            }else{
                if(array.content.$status[0] == 'RUNNING') {
                    queue += `
                        <tr><td>
                            <a href="#" onclick="return false;"><span class="downloader mouse" data-source="jdownloader" data-action="pause" data-bs-target="main"><i class="fa fa-pause"></i></span></a>
                            <a href="#" onclick="return false;"><span class="downloader mouse" data-source="jdownloader" data-action="stop" data-bs-target="main"><i class="fa fa-stop"></i></span></a>
                        </td></tr>
                        `;
                }else if(array.content.$status[0] == 'PAUSE'){
                    queue += `<tr><td><a href="#" onclick="return false;"><span class="downloader mouse" data-source="jdownloader" data-action="resume" data-bs-target="main"><i class="fa fa-fast-forward"></i></span></a></td></tr>`;
                }else{
                    queue += `<tr><td><a href="#" onclick="return false;"><span class="downloader mouse" data-source="jdownloader" data-action="start" data-bs-target="main"><i class="fa fa-play"></i></span></a></td></tr>`;
                }
                if(array.content.$status[1]) {
                    queue += `<tr><td><a href="#" onclick="return false;"><span class="downloader mouse" data-source="jdownloader" data-action="update" data-bs-target="main"><i class="fa fa-globe"></i></span></a></td></tr>`;
                }
            }
            $.each(array.content.queueItems, function(i,v) {
                count = count + 1;
                if(v.speed == null){
                    v.speed = 'Stopped';
                }
                if(v.eta == null){
                    if(v.percentage == '100'){
                        v.speed = 'Completed';
                        v.eta = '--';
                    }else{
                        v.eta = '--';
                    }
                }
                if(v.enabled == null){
                    v.speed = 'Disabled';
                }
                queue += `
                <tr>
                    <td class="max-texts">`+v.name+`</td>
                    <td>`+v.speed+`</td>
                    <td class="hidden-xs" alt="`+v.done+`">`+v.size+`</td>
                    <td class="hidden-xs">`+v.eta+`</td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: `+v.percentage+`%;" role="progressbar">`+v.percentage+`%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
            $.each(array.content.grabberItems, function(i,v) {
                count = count + 1;
                queue += `
                <tr>
                    <td class="max-texts">`+v.name+`</td>
                    <td>Online</td>
                    <td class="hidden-xs"> -- </td>
                    <td class="hidden-xs"> -- </td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: 0%;" role="progressbar">0%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
            $.each(array.content.encryptedItems, function(i,v) {
                count = count + 1;
                queue += `
                <tr>
                    <td class="max-texts">`+v.name+`</td>
                    <td>Encrypted</td>
                    <td class="hidden-xs"> -- </td>
                    <td class="hidden-xs"> -- </td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: 0%;" role="progressbar">0%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
            $.each(array.content.offlineItems, function(i,v) {
                count = count + 1;
                queue += `
                <tr>
                    <td class="max-texts">`+v.name+`</td>
                    <td>Offline</td>
                    <td class="hidden-xs"> -- </td>
                    <td class="hidden-xs"> -- </td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: 0%;" role="progressbar">0%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
            break;
		case 'sabnzbd':
            if(array.content === false){
                queue = '<tr><td class="max-texts" lang="en">Connection Error to ' + source + '</td></tr>';
                break;
            }
            if(array.content.queueItems.queue.paused){
                var state = `<a href="#" onclick="return false;"><span class="downloader mouse" data-source="sabnzbd" data-action="resume" data-bs-target="main"><i class="fa fa-play"></i></span></a>`;
                var active = 'grayscale';
            }else{
                var state = `<a href="#" onclick="return false;"><span class="downloader mouse" data-source="sabnzbd" data-action="pause" data-bs-target="main"><i class="fa fa-pause"></i></span></a>`;
                var active = '';
            }
            $('.sabnzbd-downloader-action').html(state);

            if(array.content.queueItems.queue.slots.length == 0){
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
            }
            $.each(array.content.queueItems.queue.slots, function(i,v) {
                count = count + 1;
                var action = (v.status == "Downloading") ? 'pause' : 'resume';
                var actionIcon = (v.status == "Downloading") ? 'pause' : 'play';
                queue += `
                <tr>
                    <td class="max-texts">`+v.filename+`</td>
                    <td class="hidden-xs sabnzbd-`+cleanClass(v.status)+`">`+v.status+`</td>
                    <td class="downloader mouse" data-bs-target="`+v.nzo_id+`" data-source="sabnzbd" data-action="`+action+`"><i class="fa fa-`+actionIcon+`"></i></td>
                    <td class="hidden-xs"><span class="label label-info">`+v.cat+`</span></td>
                    <td class="hidden-xs">`+v.size+`</td>
                    <td class="hidden-xs" alt="`+v.eta+`">`+v.timeleft+`</td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: `+v.percentage+`%;" role="progressbar">`+v.percentage+`%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
            if(array.content.historyItems.history.slots.length == 0){
                history = '<tr><td class="max-texts" lang="en">Nothing in history</td></tr>';
            }
            $.each(array.content.historyItems.history.slots, function(i,v) {
                history += `
                <tr>
                    <td class="max-texts">`+v.name+`</td>
                    <td class="hidden-xs sabnzbd-`+cleanClass(v.status)+`">`+v.status+`</td>
                    <td class="hidden-xs"><span class="label label-info">`+v.category+`</span></td>
                    <td class="hidden-xs">`+v.size+`</td>
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
            if(array.content === false){
                queue = '<tr><td class="max-texts" lang="en">Connection Error to ' + source + '</td></tr>';
                break;
            }
            if(array.content.queueItems.result.length == 0){
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
            }
            $.each(array.content.queueItems.result, function(i,v) {
                count = count + 1;
                var action = (v.Status == "Downloading") ? 'pause' : 'resume';
                var actionIcon = (v.Status == "Downloading") ? 'pause' : 'play';
                var percent = Math.floor((v.FileSizeMB - v.RemainingSizeMB) * 100 / v.FileSizeMB);
                var size = v.FileSizeMB * 1000000;
                v.Category = (v.Category !== '') ? v.Category : 'Not Set';
                queue += `
                <tr>
                    <td class="max-texts">`+v.NZBName+`</td>
                    <td class="hidden-xs nzbget-`+cleanClass(v.Status)+`">`+v.Status+`</td>
                    <!--<td class="downloader mouse" data-bs-target="`+v.NZBID+`" data-source="sabnzbd" data-action="`+action+`"><i class="fa fa-`+actionIcon+`"></i></td>-->
                    <td class="hidden-xs"><span class="label label-info">`+v.Category+`</span></td>
                    <td class="hidden-xs">`+humanFileSize(size,true)+`</td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: `+percent+`%;" role="progressbar">`+percent+`%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
            if(array.content.historyItems.result.length == 0){
                history = '<tr><td class="max-texts" lang="en">Nothing in history</td></tr>';
            }
            $.each(array.content.historyItems.result, function(i,v) {
                v.Category = (v.Category !== '') ? v.Category : 'Not Set';
                var size = v.FileSizeMB * 1000000;
                history += `
                <tr>
                    <td class="max-texts">`+v.NZBName+`</td>
                    <td class="hidden-xs nzbget-`+cleanClass(v.Status)+`">`+v.Status+`</td>
                    <td class="hidden-xs"><span class="label label-info">`+v.Category+`</span></td>
                    <td class="hidden-xs">`+humanFileSize(size,true)+`</td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: 100%;" role="progressbar">100%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
			break;
		case 'transmission':
            if(array.content === false){
                queue = '<tr><td class="max-texts" lang="en">Connection Error to ' + source + '</td></tr>';
                break;
            }
            if(array.content.queueItems == 0){
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
            }
            $.each(array.content.queueItems, function(i,v) {
                count = count + 1;
                switch (v.status) {
                    case 7:
                    case '7':
                        var status = 'No Peers';
                        break;
                    case 6:
                    case '6':
                        var status = 'Seeding';
                        break;
                    case 5:
                    case '5':
                        var status = 'Seeding Queued';
                        break;
                    case 4:
                    case '4':
                        var status = 'Downloading';
                        break;
                    case 3:
                    case '3':
                        var status = 'Queued';
                        break;
                    case 2:
                    case '2':
                        var status = 'Checking Files';
                        break;
                    case 1:
                    case '1':
                        var status = 'File Check Queued';
                        break;
                    case 0:
                    case '0':
                        var status = 'Complete';
                        break;
                    default:
                        var status = 'Complete';
                }
                var percent = Math.floor(v.percentDone * 100);
                v.Category = (v.Category !== '') ? v.Category : 'Not Set';
                queue += `
                <tr>
                    <td class="max-texts">`+v.name+`</td>
                    <td class="hidden-xs transmission-`+cleanClass(status)+`">`+status+`</td>
                    <td class="hidden-xs">`+v.downloadDir+`</td>
                    <td class="hidden-xs">`+humanFileSize(v.totalSize,true)+`</td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: `+percent+`%;" role="progressbar">`+percent+`%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
			break;
        case 'rTorrent':
            if(array.content === false){
                queue = '<tr><td class="max-texts" lang="en">Connection Error to ' + source + '</td></tr>';
                break;
            }
            if(array.content.queueItems == 0){
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
            }
            $.each(array.content.queueItems, function(i,v) {
                count = count + 1;
                var percent = Math.floor((v.downloaded / v.size) * 100);
                var size = v.size != -1 ? humanFileSize(v.size,false) : "?";
                var upload = v.seed !== '' ? humanFileSize(v.seed,true) : "0 B";
                var download = v.leech !== '' ? humanFileSize(v.leech,true) : "0 B";
                var upTotal = v.upTotal !== '' ? humanFileSize(v.upTotal,false) : "0 B";
                var downTotal = v.downTotal !== '' ? humanFileSize(v.downTotal,false) : "0 B";
                var date = new Date(0);
                date.setUTCSeconds(v.date);
                date = moment(date).format('LLL');
                queue += `
                <tr>
                    <td class="max-texts"><span class="tooltip-info" data-bs-toggle="tooltip" data-placement="right" title="" data-original-title="`+date+`">`+v.name+`</span></td>
                    <td class="hidden-xs rtorrent-`+cleanClass(v.status)+`">`+v.status+`</td>
                    <td class="hidden-xs"><span class="tooltip-info" data-bs-toggle="tooltip" data-placement="right" title="" data-original-title="`+downTotal+`"><i class="fa fa-download"></i>&nbsp;`+download+`</span></td>
                    <td class="hidden-xs"><span class="tooltip-info" data-bs-toggle="tooltip" data-placement="right" title="" data-original-title="`+upTotal+`"><i class="fa fa-upload"></i>&nbsp;`+upload+`</span></td>
                    <td class="hidden-xs">`+size+`</td>
                    <td class="hidden-xs"><span class="label label-info">`+v.label+`</span></td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: `+percent+`%;" role="progressbar">`+percent+`%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
            break;
        case 'utorrent':
            if(array.content === false){
                queue = '<tr><td class="max-texts" lang="en">Connection Error to ' + source + '</td></tr>';
                break;
            }
            if(array.content.queueItems == 0){
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
            }
            $.each(array.content.queueItems, function(i,v) {
		count = count + 1;
                var upload = v.upSpeed !== '' ? humanFileSize(v.upSpeed,false) : "0 B";
                var download = v.downSpeed !== '' ? humanFileSize(v.downSpeed,false) : "0 B";
		var size = v.Size !== '' ? humanFileSize(v.Size,false) : "0 B";
                queue += `
                <tr>
                    <td class="max-texts"><span class="tooltip-info" data-bs-toggle="tooltip" data-placement="right" title="">`+v.Name+`</span></td>
		    <td class="hidden-xs utorrent-`+cleanClass(v.Status)+`">`+v.Status+`</td>
                    <td class="hidden-xs"><span class="label label-info">`+v.Labels+`</span></td>
		    <td class="hidden-xs"><span class="tooltip-info" data-bs-toggle="tooltip" data-placement="right" title="" data-original-title="`+download+`"><i class="fa fa-download"></i>&nbsp;`+download+`</span></td>
                    <td class="hidden-xs"><span class="tooltip-info" data-bs-toggle="tooltip" data-placement="right" title="" data-original-title="`+upload+`"><i class="fa fa-upload"></i>&nbsp;`+upload+`</span></td>
		    <td class="hidden-xs">`+size+`</td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: `+v.Percent+`;" role="progressbar">`+v.Percent+`</div>
                        </div>
                    </td>
                </tr>
                `;
            });
            break;
		case 'sonarr':
			if(array.content === false){
				queue = '<tr><td class="max-texts" lang="en">Connection Error to ' + source + '</td></tr>';
				break;
			}
			if(array.content.queueItems == 0){
				queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
				break;
			}
			if(array.content.queueItems.records == 0){
				queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
				break;
			}
            let sonarrQueueSet = (typeof array.content.queueItems.records == 'undefined') ? array.content.queueItems : array.content.queueItems.records;
			$.each(sonarrQueueSet, function(i,v) {
				count = count + 1;
				var percent = Math.floor(((v.size - v.sizeleft) / v.size) * 100);
				percent = (isNaN(percent)) ? '0' : percent;
				var size = v.size != -1 ? humanFileSize(v.size,false) : "?";
                v.name = (typeof v.series == 'undefined') ? v.title : v.series.title;
				queue += `
                <tr>
                    <td class="">`+v.name+`</td>
                    <td class="">S`+pad(v.episode.seasonNumber,2)+`E`+pad(v.episode.episodeNumber,2)+`</td>
                    <td class="max-texts">`+v.episode.title+`</td>
                    <td class="hidden-xs sonarr-`+cleanClass(v.status)+`">`+v.status+`</td>
                    <td class="hidden-xs">`+size+`</td>
                    <td class="hidden-xs"><span class="label label-info">`+v.protocol+`</span></td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: `+percent+`%;" role="progressbar">`+percent+`%</div>
                        </div>
                    </td>
                </tr>
                `;
			});
			break;
		case 'radarr':
			if(array.content === false){
				queue = '<tr><td class="max-texts" lang="en">Connection Error to ' + source + '</td></tr>';
				break;
			}
			if(array.content.queueItems == 0){
				queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
				break;
			}
			if(array.content.queueItems.records == 0){
				queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
				break;
			}
			let queueSet = (typeof array.content.queueItems.records == 'undefined') ? array.content.queueItems : array.content.queueItems.records;
			$.each(queueSet, function(i,v) {
				count = count + 1;
				var percent = Math.floor(((v.size - v.sizeleft) / v.size) * 100);
				percent = (isNaN(percent)) ? '0' : percent;
				var size = v.size != -1 ? humanFileSize(v.size, false) : "?";
				v.name = (typeof v.movie == 'undefined') ? v.title : v.movie.title;
				queue += `
                <tr>
                    <td class="max-texts">${v.name}</td>
                    <td class="hidden-xs sonarr-${cleanClass(v.status)}">${v.status}</td>
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
			if(queue == ''){
				queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
			}
			break;
		case 'qBittorrent':
		    if(array.content === false){
                queue = '<tr><td class="max-texts" lang="en">Connection Error to ' + source + '</td></tr>';
                break;
            }
            if(array.content.queueItems == 0){
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
            }
            $.each(array.content.queueItems, function(i,v) {
                count = count + 1;
                switch (v.state) {
                    case 'stalledDL':
                        var status = 'No Peers';
                        break;
                    case 'metaDL':
                        var status = 'Getting Metadata';
                        break;
                    case 'uploading':
                        var status = 'Seeding';
                        break;
                    case 'queuedUP':
                        var status = 'Seeding Queued';
                        break;
                    case 'downloading':
                        var status = 'Downloading';
                        break;
                    case 'queuedDL':
                        var status = 'Queued';
                        break;
                    case 'checkingDL':
                    case 'checkingUP':
                        var status = 'Checking Files';
                        break;
                    case 'pausedDL':
                        var status = 'Paused';
                        break;
                    case 'pausedUP':
                        var status = 'Complete';
                        break;
                    default:
                        var status = 'Complete';
                }
                var percent = Math.floor(v.progress * 100);
                var size = v.total_size != -1 ? humanFileSize(v.total_size,true) : "?";
                queue += `
                <tr>
                    <td class="max-texts">`+v.name+`</td>
                    <td class="hidden-xs qbit-`+cleanClass(status)+`">`+status+`</td>
                    <td class="hidden-xs">`+v.save_path+`</td>
                    <td class="hidden-xs">`+size+`</td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: `+percent+`%;" role="progressbar">`+percent+`%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
			break;
		case 'deluge':
            if(array.content === false){
                queue = '<tr><td class="max-texts" lang="en">Connection Error to ' + source + '</td></tr>';
                break;
            }
            if(array.content.queueItems.length == 0){
                queue = '<tr><td class="max-texts" lang="en">Nothing in queue</td></tr>';
            }
            $.each(array.content.queueItems, function(i,v) {
                count = count + 1;
                var percent = Math.floor(v.progress);
                var size = v.total_size != -1 ? humanFileSize(v.total_size,true) : "?";
                var upload = v.upload_payload_rate != -1 ? humanFileSize(v.upload_payload_rate,true) : "?";
                var download = v.download_payload_rate != -1 ? humanFileSize(v.download_payload_rate,true) : "?";
                var action = (v.Status == "Downloading") ? 'pause' : 'resume';
                var actionIcon = (v.Status == "Downloading") ? 'pause' : 'play';
                queue += `
                <tr>
                    <td class="max-texts">`+v.name;
		    if (v.tracker_status != "") queue += `<i class="fa fa-caret-down ml-2" style="cursor:pointer" onclick="$(this).toggleClass('fa-caret-down');$(this).toggleClass('fa-caret-up');$('#status-`+v.hash+`').toggleClass('d-none');" aria-hidden="true"></i><br /><div class="well mb-0 mt-2 p-3 d-none" id="status-`+v.hash+`">`+v.tracker_status+`</div>`;
		    queue +=`</td>
                    <td class="hidden-xs deluge-`+cleanClass(v.state)+`">`+v.state+`</td>
                    <td class="hidden-xs">`+size+`</td>
                    <td class="hidden-xs"><i class="fa fa-download"></i>&nbsp;`+download+`</td>
                    <td class="hidden-xs"><i class="fa fa-upload"></i>&nbsp;`+upload+`</td>
                    <td class="text-right">
                        <div class="progress progress-lg m-b-0">
                            <div class="progress-bar progress-bar-info" style="width: `+percent+`%;" role="progressbar">`+percent+`%</div>
                        </div>
                    </td>
                </tr>
                `;
            });
			break;
		default:
			return false;
	}
    if(queue !== ''){
        $('.'+source+'-queue').html(queue);
    }
    if(history !== ''){
        $('.'+source+'-history').html(history);
    }
    $('#count-'+source).html(count);
}