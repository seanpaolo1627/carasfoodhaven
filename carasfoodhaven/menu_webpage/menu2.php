<?php
// menu.php

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'login/connect.php'; // Includes session_start()

// Include Google Client Library and Configuration
require_once 'login/google-config.php'; // Adjust path as necessary

// If 'code' parameter is set, process the OAuth callback
if (isset($_GET['code'])) {
    try {
        // Exchange authorization code for access token
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    } catch (Exception $e) {
        $_SESSION['error'] = "Error fetching access token: " . $e->getMessage();
        header("Location: login/index.php");
        exit();
    }

    if (isset($token['error'])) {
        $_SESSION['error'] = "Error fetching access token: " . htmlspecialchars($token['error']);
        header("Location: login/index.php");
        exit();
    }

    // Set the access token
    $client->setAccessToken($token['access_token']);

    // Retrieve user information from Google
    $google_oauth = new Google_Service_Oauth2($client);
    try {
        $google_account_info = $google_oauth->userinfo->get();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error fetching user info: " . $e->getMessage();
        header("Location: login/index.php");
        exit();
    }

    $email = strtolower(trim($google_account_info->email));
    $firstName = trim($google_account_info->givenName);
    $lastName = trim($google_account_info->familyName);

    // Check if the user already exists in the database
    $checkUserQuery = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($checkUserQuery);
    if(!$stmt){
        $_SESSION['error'] = "Database error: " . $conn->error;
        header("Location: login/index.php");
        exit();
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0){
        // User exists, log them in
        $_SESSION['email'] = $email;
    } else {
        // User does not exist, create a new user
        // Since Google provides minimal info, set default values for other fields
        $defaultContactNumber = 'N/A';
        $defaultAddress = 'N/A';
        $defaultPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT); // Random password

        $insertUserQuery = "INSERT INTO users (firstName, lastName, email, password, contactNumber, address)
                            VALUES (?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertUserQuery);
        if(!$insertStmt){
            $_SESSION['error'] = "Database error: " . $conn->error;
            header("Location: login/index.php");
            exit();
        }
        $insertStmt->bind_param('ssssss', $firstName, $lastName, $email, $defaultPassword, $defaultContactNumber, $defaultAddress);
        if($insertStmt->execute()){
            $_SESSION['email'] = $email;
        } else {
            $_SESSION['error'] = "Error creating user: " . $insertStmt->error;
            header("Location: login/index.php");
            exit();
        }
        $insertStmt->close();
    }
    $stmt->close();

    // After processing, redirect to menu.php without 'code' parameter to prevent duplicate processing
    header("Location: menu.php");
    exit();
}

// Check if the user is logged in via session
if (!isset($_SESSION['email'])) {
    header("Location: login/index.php"); // Redirect to login page
    exit();
}

$email = strtolower(trim($_SESSION['email'])); // Ensure email is lowercase
$userName = "Guest";

// Fetch user's first and last name from the database
$query = $conn->prepare("SELECT firstName, lastName FROM users WHERE email = ?");
if ($query) {
    $query->bind_param('s', $email);
    $query->execute();
    $result = $query->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $userName = $row['firstName'] . ' ' . $row['lastName'];
    }
    $query->close();
} else {
    // Handle query preparation error
    // Optionally, log the error and keep userName as "Guest"
}

// Fetch menu categories from the database
$menuCategories = [];
$categoryQuery = "SELECT ID, name FROM menu_category";
$categoryResult = $conn->query($categoryQuery);
if ($categoryResult) {
    while ($categoryRow = $categoryResult->fetch_assoc()) {
        $menuCategories[] = $categoryRow;
    }
}

// Fetch menu items from the database
$menuItems = [];
$itemQuery = "SELECT mi.ID, mi.name, mi.image, mi.price, mi.description, mi.menu_category
              FROM menu_item mi
              WHERE mi.menu_item_status = 'AVAILABLE'";
$itemResult = $conn->query($itemQuery);
if ($itemResult) {
    while ($itemRow = $itemResult->fetch_assoc()) {
        $menuItems[] = $itemRow;
    }
}

// Close the database connection
$conn->close();

?>
<!DOCTYPE html>
<html>

  <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
      <link rel="stylesheet" href="menu.css">
      <script src="menu.js"></script>
      <title> Menu Page | Cara's Food Haven </title>
      <link rel="shortcut icon" type="x-icon" href="../RestaurantLogo-Cut.png">
  </head>

  <body>
      
    <!-- ============================================================ -->
    <!-- ============================================================ -->
    <!-- ============================================================ -->
    
    <nav>
      <span> <img src="../RestaurantLogo-Cut.png" class="RestaurantLogo_Nav"> </span>
      <div class="search-bar">
          <input type="text" placeholder="What are you looking for?">
          <button type="submit">Search</button>
      </div>
      <ul>
        <li><a href="#"><i class="fas fa-home"></i> <span>Home</span></a></li>
        <li><a href="#" id="current-page"><i class="fas fa-utensils"></i> <span>Menu</span></a></li>
        <li><a href="#"><i class="fas fa-receipt"></i> <span>My Orders</span></a></li>
        <li><a href="#"><i class="fas fa-info-circle"></i> <span>About Us</span></a></li>
      </ul>
      <ul>
            <li class="account-dropdown">
                <div class="account-info">
                    <img id="account-img" src="../account-img-placeholder.jpg" alt="Avatar">
                    <div>
                        <span class="account-name"><?php echo htmlspecialchars($userName); ?></span>
                        <span class="account-role">User</span>
                    </div>
                    <i class="fas fa-caret-down" id="dropdown-arrow"></i>
                </div>
                <div class="dropdown-content">
                    <a href="login/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </li>
        </ul>
    </nav>
      
    <!-- ============================================================ -->
    <!-- ============================================================ -->
    <!-- ============================================================ -->

    <!-- Vertical Menu -->
    <div class="verticalmenu_container">
      <div class="verticalmenu-section">
        <label class="verticalmenu-label">MENU CATEGORIES</label>
        <?php foreach ($menuCategories as $category): ?>
            <button class="verticalmenu-btn" id="category-<?php echo htmlspecialchars($category['ID']); ?>">
              <i class="fas fa-utensils"></i> <?php echo htmlspecialchars($category['name']); ?>
              <span class="notification-badge" style="display: none;">0</span>
            </button>
        <?php endforeach; ?>
      </div>
    </div>
        
        
    <!-- ============================================================ -->
    <!-- ============================================================ -->
    <!-- ============================================================ -->

    <div id="confirm-order-modal" class="modal">
      <div id="confirm-order-content" class="modal-content">
  
          <form id="customer-form">
            <h2 id="confirm-order-header">CUSTOMER FORM</h2>
            <br>
            <!-- Order Type Selection -->
            <div class="form-group">
                <label for="order-type">Order Type:</label>
                <select id="order-type" name="order-type" required>
                  <option value="" disabled selected>--- Select the Order Type ---</option>
                  <option value="DINE IN">DINE IN</option>
                  <option value="TAKEOUT">TAKEOUT</option>
                  <option value="DELIVERY">DELIVERY</option>
                </select>
            </div>

            <!-- Payment Method Selection -->
            <div class="form-group" style="display: none">
                <label for="cash-amount">Cash Amount:</label>
                <input type="number" id="cash-amount" name="cash-amount" placeholder="Enter cash amount" min="0">
            </div>



            <!-- Customer Name -->
            <div class="form-group">
                <label for="customer-name">Customer Name:</label>
                <input type="text" id="customer-name" name="customer-name" placeholder="Enter your name" required>
            </div>

            <!-- Contact Number -->
            <div class="form-group">
                <label for="contact-number">Contact Number:</label>
                <input type="tel" id="contact-number" name="contact-number" placeholder="Enter your contact number" required>
            </div>

            <!-- Address -->
            <div class="form-group" style="display: none">
                <label for="address">Address:</label>
                <input type="text" id="address" name="address" placeholder="Enter your address">
            </div>

            <!-- Email -->
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" placeholder="Enter your email">
            </div>
            <br>

            <button type="submit" id="confirm-order-btn">Confirm Details</button>


            
          </form>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- ============================================================ -->
    <!-- ============================================================ -->

    <div id="menu-item-modal" class="modal">
      <div id="menu-item-content" class="modal-content">
          <!-- Image Placeholder -->
          <div class="image-placeholder" style="text-align: center;">
              <img src="../img_placeholder.png" alt="Menu Item Image" id="modal-menu-item-img">
          </div>
          
          <br>
          
          <!-- Header -->
          <h2 id="menu-item-header" style="text-align: center;">MENU ITEM NAME</h2>
          
          <br>

          <!-- Price -->
          <p id="menu-item-price" style="text-align: center; font-weight: bold;">PRICE</p>

          <br>
          <br>
          <br>
          
          <!-- Description -->
          <p id="menu-item-desc" style="text-align: center;">MENU ITEM DESCRIPTION TEMPLATE</p>
      </div>
  </div>

    <!-- ============================================================ -->
    <!-- ============================================================ -->
    <!-- ============================================================ -->

    <!-- Notification Container -->
    <div id="notification-container"></div>

    <!-- ============================================================ -->
    <!-- ============================================================ -->
    <!-- ============================================================ -->









    
    <!-- ============================================================ -->
    <!-- ============================================================ -->
    <!-- ============================================================ -->

    <!-- Main Container -->
    <div class="container">

      <!-- Left Side (Menu Items) -->
      <div class="container-left-side">

        <!-- =============== -->

        <!-- Header -->
        <div class="header_wrapper">
          <div class="header_container">
            <h2 id="menu-category-header">Menu</h2>
          </div>
        </div>

        <!-- =============== -->

        <!-- Card Container -->
        <div class="card-container" id="card-container">
          <?php foreach ($menuItems as $item): ?>
            <div class="card category-<?php echo htmlspecialchars($item['menu_category']); ?>" data-category="category-<?php echo htmlspecialchars($item['menu_category']); ?>" id="item-<?php echo htmlspecialchars($item['ID']); ?>">
              <div class="card-img-container">
                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="Menu Item Image" onclick="openMenuItemModal(<?php echo htmlspecialchars($item['ID']); ?>)">
              </div>
              <h1><?php echo htmlspecialchars($item['name']); ?></h1>
              <p>Php <?php echo htmlspecialchars(number_format($item['price'], 2)); ?></p>
              <div class="quantity-controls">
                <button class="quantity-button" onclick="decrementQuantity(this)">-</button>
                <input type="text" class="quantity-input" placeholder="Qty" value="0" disabled>
                <button class="quantity-button" onclick="incrementQuantity(this)">+</button>
              </div>
              <!-- Hidden fields for modal -->
              <input type="hidden" class="item-description" value="<?php echo htmlspecialchars($item['description']); ?>">
              <input type="hidden" class="item-image" value="<?php echo htmlspecialchars($item['image']); ?>">
              <input type="hidden" class="item-price" value="<?php echo htmlspecialchars($item['price']); ?>">
            </div>
          <?php endforeach; ?>
        </div>

      </div>
      <!-- ============================================================ -->

      <div class="container-right-side">

        <h2 class="right-side-header">ORDER SUMMARY</h2>
    
        <table class="order-summary-table">
          <thead>
              <tr>
                  <th>Menu Item</th>
                  <th>Price</th>
                  <th>Qty</th>
                  <th>Subtotal</th>
              </tr>
          </thead>
          <tbody>
            <!-- Empty message row -->
            <tr class="empty-message-row">
                <td colspan="4" class="empty-message">THIS IS WHERE YOUR ORDERS ARE DISPLAYED.</td>
            </tr>

            <!-- <tr>
                <td>???</td>
                <td>Php ???.??</td>
                <td>?</td>
                <td>Php ???.??</td>
            </tr> -->

            <!-- Total row -->
            <tr class="total-row">
                <td colspan="3" class="total-label">Total</td>
                <td class="total-amount">Php 0</td>
            </tr>

            <!-- Discount Code row -->
            <tr class="discount-row">
                <td colspan="3" class="discount-label">Discount Code</td>
                <td><input type="text" placeholder="Enter code here"></td>
            </tr>
          </tbody>
        </table>
    
        <br>
        <button class="confirm-order-button">Confirm Order</button>

        <br>
        <br>
        <br>
      </div>

      <!-- ============================================================ -->

    </div>
      
    <!-- ============================================================ -->
    <!-- ============================================================ -->
    <!-- ============================================================ -->







    <!-- ============================================================ -->
    <!-- ============================================================ -->
    <!-- ============================================================ -->

    <script>
  // JavaScript code to handle category selection and menu item display

  const accountDropdown = document.querySelector('.account-dropdown');
        const dropdownArrow = document.getElementById('dropdown-arrow');

        accountDropdown.addEventListener('click', function(event) {
          event.stopPropagation(); // Prevent click from bubbling up
          accountDropdown.classList.toggle('active'); 

          // Toggle the arrow direction
          if (accountDropdown.classList.contains('active')) {
            dropdownArrow.classList.remove('fa-caret-down');
            dropdownArrow.classList.add('fa-caret-up');
          } else {
            dropdownArrow.classList.remove('fa-caret-up');
            dropdownArrow.classList.add('fa-caret-down');
          }
        });


  document.addEventListener("DOMContentLoaded", function() {

    // Add event listeners to vertical menu buttons
    const verticalMenuButtons = document.querySelectorAll(".verticalmenu-btn");
    const cards = document.querySelectorAll(".card");
    const categoryHeader = document.getElementById("menu-category-header");
    updateCategoryCounts();

    verticalMenuButtons.forEach(button => {
      button.addEventListener("click", function () {
        const targetCategory = button.id; // Get the clicked button's id

        // Set the category header text to the button's text content
        categoryHeader.textContent = button.textContent.trim();

        // Toggle 'active' class for the buttons
        verticalMenuButtons.forEach(btn => btn.classList.remove("active"));
        button.classList.add("active");

        // Hide all cards and show only the ones with the matching data-category
        cards.forEach(card => {
          if (card.getAttribute('data-category') === targetCategory) {
            card.style.display = "block"; // Show matching cards
          } else {
            card.style.display = "none"; // Hide non-matching cards
          }
        });
      });
    });

    // Automatically click the first button on page load
    if (verticalMenuButtons.length > 0) {
      verticalMenuButtons[0].click(); // Click the first button
    }

    // Function to open the menu item modal with details
    window.openMenuItemModal = function(itemId) {
      const modal = document.getElementById('menu-item-modal');
      const modalContent = document.getElementById('menu-item-content');

      // Find the card with the matching item ID
      const card = document.getElementById('item-' + itemId);
      if (card) {
        const itemName = card.querySelector('h1').textContent;
        const itemPrice = card.querySelector('.item-price').value;
        const itemDescription = card.querySelector('.item-description').value;
        const itemImageSrc = card.querySelector('.item-image').value;

        // Set modal content
        document.getElementById('menu-item-header').textContent = itemName;
        document.getElementById('menu-item-price').textContent = 'Php ' + parseFloat(itemPrice).toFixed(2);
        document.getElementById('menu-item-desc').textContent = itemDescription;
        document.getElementById('modal-menu-item-img').src = itemImageSrc;

        // Show modal
        modal.classList.add('show');
      }
    };

    // Close modal when clicking outside of the modal content
      window.addEventListener('click', function(event) {
    const menuItemModal = document.getElementById('menu-item-modal');
  if (event.target === menuItemModal) {
    menuItemModal.classList.remove('show');
  }
    });

    function updateCategoryCounts() {
      // Initialize a counts object
      const categoryCounts = {};

  // Get all cards
  const cards = document.querySelectorAll('.card');

  cards.forEach(card => {
    const quantityInput = card.querySelector('.quantity-input');
    const quantity = parseInt(quantityInput.value) || 0;
    const category = card.getAttribute('data-category'); // e.g., 'category-1'

    if (!categoryCounts[category]) {
      categoryCounts[category] = 0;
    }

    categoryCounts[category] += quantity;
  });

  // Update the notification badges
  const verticalMenuButtons = document.querySelectorAll('.verticalmenu-btn');

  verticalMenuButtons.forEach(button => {
    const category = button.id; // e.g., 'category-1'
    const count = categoryCounts[category] || 0;
    const badge = button.querySelector('.notification-badge');

    if (count > 0) {
      badge.style.display = 'block';
      badge.textContent = count;
      button.classList.add('has-items');
    } else {
      badge.style.display = 'none';
      badge.textContent = '';
      button.classList.remove('has-items');
        }
      });
    }


    
    // Quantity increment and decrement functions
    function incrementQuantity(button) {
  const quantityInput = button.parentElement.querySelector('.quantity-input');
  let quantity = parseInt(quantityInput.value) || 0;
  quantity++;
  quantityInput.value = quantity;

  const card = button.closest('.card');
  if (quantity > 0) {
    card.classList.add('selected');
  }

  updateOrderSummary();
  updateCategoryCounts();
}

function decrementQuantity(button) {
  const quantityInput = button.parentElement.querySelector('.quantity-input');
  let quantity = parseInt(quantityInput.value) || 0;
  if (quantity > 0) {
    quantity--;
    quantityInput.value = quantity;

    const card = button.closest('.card');
    if (quantity === 0) {
      card.classList.remove('selected');
    }

    updateOrderSummary();
    updateCategoryCounts();
  }
}
    // Make functions accessible globally
    window.incrementQuantity = incrementQuantity;
    window.decrementQuantity = decrementQuantity;

    // Function to update the order summary
    function updateOrderSummary() {
      const orderSummaryTable = document.querySelector('.order-summary-table tbody');
      const totalRow = orderSummaryTable.querySelector('.total-row');
      const discountRow = orderSummaryTable.querySelector('.discount-row');
      const emptyMessageRow = orderSummaryTable.querySelector('.empty-message-row');

  // Remove existing rows except for total and discount rows
      const existingRows = orderSummaryTable.querySelectorAll('tr:not(.total-row):not(.discount-row):not(.empty-message-row)');
    existingRows.forEach(row => row.remove());

  let totalAmount = 0;
  let hasItems = false;

  cards.forEach(card => {
    const quantityInput = card.querySelector('.quantity-input');
    let quantity = parseInt(quantityInput.value) || 0;
    if (quantity > 0) {

      hasItems = true;
      const itemName = card.querySelector('h1').textContent;
      const itemPrice = parseFloat(card.querySelector('.item-price').value);
      const subtotal = itemPrice * quantity;
      card.classList.add('selected');
      totalAmount += subtotal;

      // Create new row
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${itemName}</td>
        <td>Php ${itemPrice.toFixed(2)}</td>
        <td>${quantity}</td>
        <td>Php ${subtotal.toFixed(2)}</td>
      `;
      orderSummaryTable.insertBefore(row, totalRow);
    }
  });

  // Update total amount
  totalRow.querySelector('.total-amount').textContent = 'Php ' + totalAmount.toFixed(2);

  // Show or hide empty message
  if (hasItems) {
    emptyMessageRow.style.display = 'none';
  } else {
    emptyMessageRow.style.display = '';
  }

  // Show or hide the Confirm Order button
  const confirmOrderButton = document.querySelector('.confirm-order-button');
  if (hasItems) {
    confirmOrderButton.style.display = 'block';
  } else {
    confirmOrderButton.style.display = 'none';
  }
}


    // Event listener for confirm order button
    document.querySelector('.confirm-order-button').addEventListener('click', function() {
      // Check if there are items in the order summary
      const orderSummaryTable = document.querySelector('.order-summary-table tbody');
      const existingRows = orderSummaryTable.querySelectorAll('tr:not(.total-row):not(.discount-row):not(.empty-message-row)');
      if (existingRows.length === 0) {
        alert('Please add items to your order before confirming.');
        return;
      }
      // Open confirm order modal
      document.getElementById('confirm-order-modal').classList.add('show');
    });

    // Close confirm order modal when clicking outside of the modal content
    window.addEventListener('click', function(event) {
  const confirmOrderModal = document.getElementById('confirm-order-modal');
  if (event.target === confirmOrderModal) {
    confirmOrderModal.classList.remove('show');
  }
});

    // Handle order type change in confirm order modal
    document.getElementById('order-type').addEventListener('change', function () {
      const orderType = this.value;

      const cashAmountInput = document.getElementById('cash-amount');
      const cashAmountGroup = cashAmountInput.closest('.form-group'); // Cash Amount wrapper div
      const addressInput = document.getElementById('address');
      const contactNumberInput = document.getElementById('contact-number');

      // Reset visibility and required attributes
      cashAmountGroup.style.display = 'none'; // Hide cash amount field initially
      addressInput.closest('.form-group').style.display = 'none'; // Hide address field
      contactNumberInput.removeAttribute('required'); // Reset required attribute
      cashAmountInput.removeAttribute('required'); // Reset required attribute

      // Apply specific rules based on order type
      if (orderType === 'DINE IN' || orderType === 'TAKEOUT') {
        cashAmountGroup.style.display = 'block'; // Show cash amount field
        cashAmountInput.setAttribute('required', 'true'); // Make cash amount required
      } 
      if (orderType === 'DELIVERY') {
        addressInput.closest('.form-group').style.display = 'block'; // Show address field
        contactNumberInput.setAttribute('required', 'true'); // Make contact number required
      }
    });

    // Handle form submission in confirm order modal
    document.getElementById('customer-form').addEventListener('submit', function(event) {
      event.preventDefault();
      // Collect form data and proceed with order processing
      alert('Order confirmed! Thank you for your purchase.');
      // Close the modal
      document.getElementById('confirm-order-modal').classList.add('show');
      // Reset quantities and order summary
      resetOrder();
    });

    // Function to reset the order
    function resetOrder() {
  // Reset quantity inputs and remove 'selected' class
  cards.forEach(card => {
    const quantityInput = card.querySelector('.quantity-input');
    quantityInput.value = 0;
    card.classList.remove('selected');
  });
  // Update order summary and category counts
  updateOrderSummary();
  updateCategoryCounts();
}


  });
</script>

      
    <!-- ============================================================ -->
    <!-- ============================================================ -->
    <!-- ============================================================ -->
    
  </body>

</html>