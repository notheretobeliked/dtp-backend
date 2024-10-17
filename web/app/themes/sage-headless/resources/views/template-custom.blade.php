{{--
  Template Name: CSV upload
--}}

@extends('layouts.app')

@section('content')
    @while (have_posts())
        @php(the_post())
        @include('partials.page-header')
        <div class="max-w-screen-md mx-auto">
          <h3 class="text-[2rem] my-10">Import csv files</h3>
        <form id="csv-upload-form" action="{{ admin_url('admin-ajax.php') }}" method="post" class="flex flex-col gap-3" enctype="multipart/form-data">
          <div class="grid grid-cols-2">  
          <label for="csv_file">Upload CSV:</label>
            <input type="file" class="border border-black" name="csv_file" id="csv_file" accept=".csv" required>
        </div>
            <div class="grid grid-cols-2">
            <label for="taxonomy">Choose Taxonomy:</label>
            <select  class="border border-black" name="import_type" id="import_type" required>
                <option value="person">People</option>
                <option value="collection">Collection</option>
                <option value="publisher">Publisher</option>
                <option value="books">Master CSV (Books)</option> <!-- New option for books -->
                <option value="update_acf_text_fields">Update ACF Text Fields</option> <!-- New option for updating ACF text fields -->

            </select>
            </div>
            <input type="hidden" name="action" value="import_data">
            <input type="submit"  class="border border-black" value="Import data" />
        </form>

        <div id="upload-result"></div>

        <h3 class="text-[2rem] my-10">Images</h3>
        <form id="image-upload-form" class="flex flex-col gap-3" action="{{ admin_url('admin-ajax.php') }}" method="post" enctype="multipart/form-data">
          <div class="grid grid-cols-2">
          <label for="image_directory">Image Directory Path:</label>
          <input class="border border-black"  type="text" name="image_directory" id="image_directory" value="/Users/erik/Downloads/dtp-webp/" required>
          </div>
          <input type="hidden" name="action" value="import_images">
          <input class="border border-black"  type="submit" value="Import images" />
      </form>
      
      <div id="image-import-result"></div>
        </div>

        <script>
            document.getElementById('csv-upload-form').addEventListener('submit', function(e) {
                e.preventDefault();

                var formData = new FormData(this);
                var resultDiv = document.getElementById('upload-result');

                fetch('{{ admin_url('admin-ajax.php') }}', { // Use the Blade syntax here
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log(data); // Inspect the response in the browser console
                        resultDiv.innerHTML = `<p>${data.message}</p>`;
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        resultDiv.innerHTML = '<p>Something went wrong. Please try again.</p>';
                    });
            });

            document.getElementById('image-upload-form').addEventListener('submit', function(e) {
                e.preventDefault();

                var formData = new FormData(this);
                var resultDiv = document.getElementById('image-import-result');

                fetch('{{ admin_url('admin-ajax.php') }}', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        resultDiv.innerHTML = `<p>${data.message}</p>`;
                    })
                    .catch(error => {
                        resultDiv.innerHTML = '<p>Error occurred during image import.</p>';
                        console.error('Error:', error);
                    });
            });
        </script>
    @endwhile
@endsection
