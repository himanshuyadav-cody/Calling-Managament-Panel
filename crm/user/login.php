<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
  <div class="container d-flex justify-content-center align-items-center vh-100">
    <div class="card p-4" style="width: 100%; max-width: 400px;">
      <h2 class="text-center mb-4">Admin Login</h2>
      <div id="error-message" class="text-danger text-center"></div> <!-- Error message container -->
      <form id="login-form">
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" class="form-control" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Login</button>
      </form>
    </div>
  </div>
  
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script>
    $(document).ready(function () {
      $('#login-form').on('submit', function (event) {
        event.preventDefault(); // Prevents form from reloading the page

        // Gather form data
        const formData = {
          email: $('#username').val(),
          password: $('#password').val()
        };

        // AJAX request
        $.ajax({
          url: 'authenticate.php',
          type: 'POST',
          data: formData,
          success: function (response) {
            if (response.trim() === 'success') {
              window.location.href = 'leads_management.php'; // Redirect on successful login
            } else {
              $('#error-message').text('Invalid login credentials.'); // Show error message
            }
          },
          error: function () {
            $('#error-message').text('An error occurred. Please try again.'); // Show error on failure
          }
        });
      });
    });
  </script>
</body>
</html>
