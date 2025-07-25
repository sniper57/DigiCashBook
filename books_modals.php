<!-- ADD Book Modal -->
<div class="modal fade" id="addBookModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="books.php" class="modal-content">
      <input type="hidden" name="action" value="add_book">
      <div class="modal-header">
        <h5 class="modal-title">Add New Book</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Book Name</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Opening Balance</label>
          <input type="number" name="opening_balance" class="form-control" step="0.01" value="0.00">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Add Book</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT Book Modal -->
<div class="modal fade" id="editBookModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="books.php" class="modal-content">
      <input type="hidden" name="action" value="edit_book">
      <input type="hidden" name="book_id" id="edit_book_id">
      <div class="modal-header">
        <h5 class="modal-title">Edit Book</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Book Name</label>
          <input type="text" name="name" id="edit_book_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Opening Balance</label>
          <input type="number" name="opening_balance" id="edit_book_balance" class="form-control" step="0.01">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE Book Modal -->
<div class="modal fade" id="deleteBookModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="books.php" class="modal-content">
      <input type="hidden" name="action" value="delete_book">
      <input type="hidden" name="book_id" id="delete_book_id">
      <div class="modal-header">
        <h5 class="modal-title text-danger">Delete Book</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p class="text-danger">Are you sure you want to delete the book <strong id="delete_book_name"></strong>?</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-danger">Yes, Delete</button>
      </div>
    </form>
  </div>
</div>
