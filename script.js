$(document).ready(function() {
    $('#uploadForm').submit(function(e) {
        e.preventDefault();
        var formData = new FormData(this);

        $.ajax({
            url: 'upload.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            contentType: false,
            processData: false,
            success: function(response) {
                if (response.status === 'success') {
                    // Clear previous records
                    $('#recordsTable tbody').empty();

                    // Display records if any
                    if (response.records.length > 0) {
                        response.records.forEach(function(record, index) {
                            var rowClass = record.isValid ? '' : 'invalid-row';
                            var row = '<tr class="' + rowClass + '">';
                            row += '<td>' + (index + 1) + '</td>'; // ID
                            row += '<td>' + (record.data[0] || '') + '</td>'; // STATE - DB
                            row += '<td>' + (record.data[1] || '') + '</td>'; // CITY - DB
                            row += '<td>' + (record.data[2] || '') + '</td>'; // POP Name
                            row += '<td>' + (record.data[3] || '') + '</td>'; // POP ID
                            row += '<td>' + (record.data[4] || '') + '</td>'; // POP Address
                            row += '<td>' + (record.data[5] || '') + '</td>'; // City
                            row += '<td>' + (record.data[6] || '') + '</td>'; // State
                            row += '<td>' + (record.data[7] || '') + '</td>'; // Pincode
                            row += '<td>' + record.message + '</td>'; // Validation message
                            row += '</tr>';
                            $('#recordsTable tbody').append(row);
                        });
                        $('#recordsTable').removeClass('hidden');
                        $('h3').removeClass('hidden');
                    } else {
                        $('#recordsTable').addClass('hidden');
                        $('h3').addClass('hidden');
                    }
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error uploading file.');
            },
        });
    });
});
