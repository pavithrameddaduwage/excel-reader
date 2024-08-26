<?php include 'header.php'; ?>
    <div class="background-container">
        <div class="container">
            <h1>Upload Ladder Plan</h1>
            <form id="uploadForm" action="upload.php" method="post" enctype="multipart/form-data" target="uploadFrame">
                <div class="form-group">
                    <select id="department" name="department" required>
                        <option value="" disabled selected>Select Department</option>
                        <option value="007">007</option>
                        <option value="019">019</option>
                        <option value="063">063</option>
                    </select>
                </div>
                <div class="form-group">
                    <select id="year" name="year" required>
                        <option value="" disabled selected>Select Year</option>
                        <?php
                        $currentYear = date("Y");
                        for ($i = 0; $i < 5; $i++) {
                            $year = $currentYear - $i;
                            echo "<option value=\"$year\">$year</option>";
                        }
                        ?>
                        <!-- <option value="2024" selected>Select Year</option> -->
                    </select>
                </div>
                <div class="form-group">
                    <select id="week" name="week" required>
                        <option value="" disabled selected>Select Week</option>
                        <?php
                        for ($i = 1; $i <= 52; $i++) {
                            echo "<option value=\"$i\">Week $i</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="file-upload">
                    <label for="file" id="customFileLabel">
                        <i class="fas fa-file-upload"></i> Choose File
                    </label>
                    <input type="file" id="file" name="file" accept=".xlsx, .xls, .csv" required>
                    <div id="fileUploadName"></div>  
                </div>
                <input type="submit" name="upload" value="Upload">
                <div class="message" id="uploadMessage"></div>
                <div class="error-message" id="error-message">
                    <i class="fas fa-exclamation-circle"></i> Select a file to upload!
                </div>
            </form>
            <iframe id="uploadFrame" name="uploadFrame" style="display: none;"></iframe>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script>
    document.getElementById('file').addEventListener('change', function(event) {
        var fileInput = document.getElementById('file');
        var fileUploadName = document.getElementById('fileUploadName');
        var customFileLabel = document.getElementById('customFileLabel');
        var errorMessage = document.getElementById('error-message');

        if (fileInput.files.length > 0) {
            fileUploadName.textContent = fileInput.files[0].name;
            customFileLabel.classList.add('selected');
            errorMessage.style.display = 'none';  
        } else {
            fileUploadName.textContent = '';
            customFileLabel.classList.remove('selected');
            errorMessage.style.display = 'block';  
        }
    });

    document.getElementById('uploadForm').addEventListener('submit', function(event) {
        var fileInput = document.getElementById('file');
        var errorMessage = document.getElementById('error-message');
        var uploadMessage = document.getElementById('uploadMessage');

        if (!fileInput.value) {
            errorMessage.style.display = 'block';
            event.preventDefault();
        } else {
            errorMessage.style.display = 'none';
            uploadMessage.textContent = 'Uploading... Please wait.';
        }
    });

    document.getElementById('uploadFrame').addEventListener('load', function() {
        var iframe = document.getElementById('uploadFrame');
        var iframeContent = iframe.contentDocument || iframe.contentWindow.document;
        var responseText = iframeContent.body.textContent;

        if (responseText.includes('success')) {
            document.getElementById('uploadMessage').textContent = responseText;
        } else {
            document.getElementById('uploadMessage').textContent = 'Error: ' + responseText;
        }

     
        setTimeout(function() {
            location.reload();
        }, 7000);  
    });
</script>
