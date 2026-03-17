<footer class="footer mt-auto py-3 bg-dark text-white text-center">
    <div class="container">
      <span>Design & Develop by <a href="https://zapron.in/" target="_blank">Zapron</a></span>
    </div>
  </footer>
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script> 
  <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>  
  <script>
  $(document).ready(function() {
      $('table').DataTable({
          // You can customize your DataTable settings here
          "paging": true,  // Enables pagination
          "searching": true,  // Enables search functionality
          "ordering": true,  // Enables sorting by columns
          "info": true,  // Shows information (like showing X to Y entries)
          "autoWidth": false  // Disable auto width calculation for columns
      });
  });
  </script>