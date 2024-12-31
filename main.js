document.addEventListener('DOMContentLoaded', function() {
    loadSettings();
});

function loadSettings() {
    fetch('/api/plugin/plexlogviewer/settings')
        .then(response => response.json())
        .then(data => {
            // Populate log paths
            const logPathsDiv = document.getElementById('logPaths');
            data.logPaths.forEach(path => {
                addLogPathInput(path);
            });

            // Populate file extensions
            const fileExtensionsDiv = document.getElementById('fileExtensions');
            data.fileExtensions.forEach(ext => {
                addFileExtensionInput(ext.value);
            });
        });
}

function addLogPath(value = '') {
    const container = document.createElement('div');
    container.className = 'input-group mb-2';
    container.innerHTML = `
        <input type="text" class="form-control" name="logPaths[]" value="${value}" placeholder="Enter log path">
        <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()">Remove</button>
    `;
    document.getElementById('logPaths').appendChild(container);
}

function addFileExtension(value = '') {
    const container = document.createElement('div');
    container.className = 'input-group mb-2';
    container.innerHTML = `
        <input type="text" class="form-control" name="fileExtensions[]" value="${value}" placeholder="Enter file extension">
        <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()">Remove</button>
    `;
    document.getElementById('fileExtensions').appendChild(container);
}

document.getElementById('plexlogviewer-settings').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const settings = {
        logPaths: Array.from(formData.getAll('logPaths[]')),
        fileExtensions: Array.from(formData.getAll('fileExtensions[]'))
    };

    fetch('/api/plugin/plexlogviewer/settings', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(settings)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Settings saved successfully!');
        } else {
            alert('Error saving settings');
        }
    });
});
