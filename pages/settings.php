<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Plex Log Viewer Settings</h3>
                </div>
                <div class="card-body">
                    <form id="plexlogviewer-settings">
                        <div class="form-group">
                            <label>Log Paths</label>
                            <div id="logPaths">
                                <!-- Log paths will be dynamically added here -->
                            </div>
                            <button type="button" class="btn btn-primary btn-sm mt-2" onclick="addLogPath()">Add Log Path</button>
                        </div>
                        <div class="form-group mt-3">
                            <label>File Extensions</label>
                            <div id="fileExtensions">
                                <!-- File extensions will be dynamically added here -->
                            </div>
                            <button type="button" class="btn btn-primary btn-sm mt-2" onclick="addFileExtension()">Add File Extension</button>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">Save Settings</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
