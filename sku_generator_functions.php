<!DOCTYPE html>
<html>

<head>
 <div class="header">
    <img  style="height: 216px;
    margin-top: -79px;
    margin-left: -450px;
    width: 229px;" src="https://cdn.shopify.com/s/files/1/0787/9258/9609/files/Aqua_Agro_Logo_Png.png?v=1697432045" alt="logo" >

<h1 style="margin-top:10px;margin-left:300px;">AQUA-AGRO  DATABASE </h1>

 </div>

    <title>SKU Generator</title>

</head>
<body>



<?php
// Establish database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sku_generator";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


function getBrandID($brand) {
    global $conn;
  
    // Prepare a statement to check for existing brand
    $stmt = $conn->prepare("SELECT brand_id FROM brands WHERE brand_name = ?");
    $stmt->bind_param("s", $brand);
    $stmt->execute();
    $result = $stmt->get_result();
  
    // Check if brand exists
    if ($result->num_rows > 0) {
        // Brand exists, fetch its ID
        $row = $result->fetch_assoc();
        return $row['brand_id'];  // Return existing brand ID
    } else {
        // Brand doesn't exist, fetch the maximum ID
        $stmt = $conn->prepare("SELECT COALESCE(MAX(brand_id), 0) AS max_id FROM brands");
        $stmt->execute();
        $max_id_result = $stmt->get_result();
        $max_id_row = $max_id_result->fetch_assoc();
        $next_id = $max_id_row['max_id'] + 1;

        // Insert the new brand with the incremented ID
        $stmt = $conn->prepare("INSERT INTO brands (brand_id, brand_name) VALUES (?, ?)");
        $stmt->bind_param("is", $next_id, $brand);

        // Execute the insert statement
        if ($stmt->execute() === false) {
            // Handle insertion error
            echo "Error: Failed to insert brand into the database.";
            return null;  // Indicate error or use a default value
        } else {
            // Return the newly generated ID
            return $next_id;
        }
    }

    // Close statement (optional, recommended practice)
    $stmt->close();
}





// Function to fetch or insert collection ID
function getCollectionID($collection) {
    global $conn;
  
    // Prepare a statement to check for existing collection
    $stmt = $conn->prepare("SELECT collection_id FROM collection WHERE collection_name = ?");
    $stmt->bind_param("s", $collection);
    $stmt->execute();
    $result = $stmt->get_result();
  
    // Check if collection exists
    if ($result->num_rows > 0) {
        // Collection exists, fetch its ID
        $row = $result->fetch_assoc();
        return $row['collection_id'];  // Return existing collection ID
    } else {
        // Collection doesn't exist, fetch the maximum ID
        $stmt = $conn->prepare("SELECT COALESCE(MAX(collection_id), 0) AS max_id FROM collection");
        $stmt->execute();
        $max_id_result = $stmt->get_result();
        $max_id_row = $max_id_result->fetch_assoc();
        $next_id = $max_id_row['max_id'] + 1;

        // Insert the new collection with the incremented ID
        $stmt = $conn->prepare("INSERT INTO collection (collection_id, collection_name) VALUES (?, ?)");
        $stmt->bind_param("is", $next_id, $collection);

        // Execute the insert statement
        if ($stmt->execute() === false) {
            // Handle insertion error
            echo "Error: Failed to insert collection into the database.";
            return null;  // Indicate error or use a default value
        } else {
            // Return the newly generated ID
            return $next_id;
        }
    }

    // Close statement (optional, recommended practice)
    $stmt->close();
}


// Function to fetch or insert category ID
function getCategoryID($category, $collection_id) {
    global $conn;

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Check if the category already exists in the collection
        $stmt = $conn->prepare("SELECT sequence_number FROM category WHERE category_name = ? AND collection_id = ?");
        $stmt->bind_param("si", $category, $collection_id);
        $stmt->execute();
        $result = $stmt->get_result();

        // If the category already exists, fetch its sequence number
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $sequence_number = $row['sequence_number'];
        } else {
            // If the category does not exist, determine the next sequence number
            $stmt = $conn->prepare("SELECT MAX(sequence_number) AS max_sequence FROM category WHERE collection_id = ?");
            $stmt->bind_param("i", $collection_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $sequence_number = ($row['max_sequence'] === null) ? 1 : ($row['max_sequence'] + 1);

            // Construct the category ID
            $category_id = str_pad($collection_id, 2, '0', STR_PAD_LEFT) . '-' . str_pad($sequence_number, 2, '0', STR_PAD_LEFT);

            // Insert the new category into the database
            $stmt = $conn->prepare("INSERT INTO category (category_name, collection_id, category_id, sequence_number) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siis", $category, $collection_id, $category_id, $sequence_number);
            $stmt->execute();
        }

        // Format the sequence number with leading zeros
        $formatted_sequence_number = str_pad($sequence_number, 2, '0', STR_PAD_LEFT);

        // Construct the SKU
        $sku = $formatted_sequence_number;

        // Commit transaction
        $conn->commit();

        // Return the SKU
        return $sku;
    } catch (mysqli_sql_exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e; // Re-throw the exception
    }
}





// Function to fetch or insert subcategory ID
function getSubcategoryID($category_id, $collection_id, $subcategory_name) {
    global $conn;

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Check if the subcategory already exists for the given category and collection
        $stmt = $conn->prepare("SELECT subcategory_id, sequence_number FROM subcategory WHERE category_id = ? AND collection_id = ? AND subcategory_name = ?");
        $stmt->bind_param("iis", $category_id, $collection_id, $subcategory_name);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Subcategory already exists, fetch its ID and sequence number
            $row = $result->fetch_assoc();
            $subcategory_id = $row['subcategory_id'];
            $sequence_number = $row['sequence_number'];
        } else {
            // Determine the next sequence number for the subcategory within the same category and collection
            $stmt = $conn->prepare("SELECT MAX(sequence_number) AS max_sequence FROM subcategory WHERE category_id = ? AND collection_id = ?");
            $stmt->bind_param("ii", $category_id, $collection_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $sequence_number = ($row['max_sequence'] === null) ? 1 : ($row['max_sequence'] + 1);

            // Insert the new subcategory into the database
            $stmt = $conn->prepare("INSERT INTO subcategory (category_id, collection_id, subcategory_name, sequence_number) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisi", $category_id, $collection_id, $subcategory_name, $sequence_number);
            $stmt->execute();

            // Get the ID of the inserted subcategory
            $subcategory_id = $stmt->insert_id;
        }

        // Generate the subcategory ID based on the sequence number
        $subcategory_id = $sequence_number;

        // Update the subcategory record with the generated subcategory ID
        $stmt = $conn->prepare("UPDATE subcategory SET subcategory_id = ? WHERE subcategory_id = ?");
        $stmt->bind_param("si", $subcategory_id, $subcategory_id);
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        // Return the subcategory ID
        return $subcategory_id;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e; // Re-throw the exception
    }
}







// Function to fetch or insert product ID
function getProductID($productname, $category_id, $collection_id, $brand_id, $subcategory_id) {
    global $conn;

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Check if the product name already exists in the same subcategory and brand
        $stmt = $conn->prepare("SELECT product_id FROM productname WHERE product_name = ? AND subcategory_id = ? AND brand_id = ?");
        $stmt->bind_param("sii", $productname, $subcategory_id, $brand_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Product with the same name already exists in the subcategory and brand
            // Retrieve the existing product ID
            $row = $result->fetch_assoc();
            $product_id = $row['product_id'];

            // Commit transaction
            $conn->commit();

            // Return existing product ID
            return $product_id;
        }

        // Determine the next sequence number for the product within the subcategory and brand
        $stmt = $conn->prepare("SELECT MAX(sequence_number) AS max_sequence FROM productname WHERE subcategory_id = ? AND brand_id = ?");
        $stmt->bind_param("ii", $subcategory_id, $brand_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $sequence_number = ($row['max_sequence'] === null) ? 0 : $row['max_sequence'];

        // Increment the sequence number
        $sequence_number++;

        // Construct the product ID
        $product_id = str_pad($sequence_number, 2, '0', STR_PAD_LEFT);

        // Insert the new product into the database
        $stmt = $conn->prepare("INSERT INTO productname (subcategory_id, brand_id, product_name, sequence_number, product_id ) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisis", $subcategory_id, $brand_id, $productname, $sequence_number, $product_id );
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        // Return the product ID
        return $product_id;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e; // Re-throw the exception
    }
}
























// Handle file upload
// Handle file upload
if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $csvFile = $_FILES['file']['tmp_name'];
    $handle = fopen($csvFile, "r");

    // Skip the header row
    fgetcsv($handle);
    
    // Print HTML table header
    echo "<table border='1'>";
    echo "<tr><th>Brand</th><th>Collection</th><th>Category</th><th>Subcategory</th><th>Product Name</th><th>SKU</th><th>Title</th></tr>";

    // Read and process each row
    while (($row = fgetcsv($handle)) !== false) {
        $brand = $row[0];
        $collection = $row[1];
        $category = $row[2];
        $subcategory = $row[3];
        $productname = $row[4];
        
        
        // Get IDs for collection, category, subcategory, and productname
        $brand_id = getBrandID($brand);
        $collection_id = getCollectionID($collection);
        $category_id = getCategoryID($category, $collection_id);
        $subcategory_id = getSubcategoryID($subcategory, $category_id ,$subcategory);
        $product_id = getProductID($productname, $category_id, $collection_id, $brand_id, $subcategory_id);
        $brand_initials = substr($brand, 0, 2);
        

        // Check if SKU already exists in the database
        $existing_sku_query = $conn->prepare("SELECT * FROM sku_generator WHERE sku = ?");
        $existing_sku_query->bind_param("s", $sku);
        $sku =  $collection_id . "-" . $brand_initials . "-" . $category_id . "-" .  $subcategory_id. "-" . $product_id;
        $existing_sku_query->execute();
        $existing_sku_result = $existing_sku_query->get_result();
        $title = sprintf("%-2s | %-2s | %-2s | %-2s | %-2s | %-2s\n", $brand, $collection, $category, $subcategory, $productname,$sku );//$sku

        if ($existing_sku_result->num_rows == 0) {
            // SKU does not exist, insert data into the database
            $stmt = $conn->prepare("INSERT INTO sku_generator (brand, collection, category, sub_category, product_name, sku, title, collection_id, category_id, subcategory_id, productname_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssiiis", $brand, $collection, $category, $subcategory, $productname, $sku, $title, $collection_id, $category_id, $subcategory_id, $product_id);

            $stmt->execute();
            $stmt->close();
        }

        // Output row in HTML table format
        echo "<tr><td>$brand</td><td>$collection</td><td>$category</td><td>$subcategory</td><td>$productname</td><td>$sku</td><td>$title</td></tr>";
    }

    // Print HTML table footer
    echo "</table>";

    // Close file handle
    fclose($handle);
}

?>

    <form style="margin-left:37% !important;" method="post" enctype="multipart/form-data">
        <input type="file" name="file" accept=".csv">
        <button type="submit">Generate SKUs</button>
      <br>  <br>  <br>  <br>  <br>  <br>




      <div class="parent" style="display:inline-flex; float:center;margin-left:-550px;">
      <div class="col-md-3">
            <div class="form-group"><label control-label">choose BRAND</label>
                <select class="form-control" name="brands">
                    <option>select</option>
                    <?php
                    $brands = []; // create an empty array to store unique brands

                    $query = "SELECT DISTINCT BRAND FROM sku_generator"; // Use DISTINCT to get unique brands
                    $result = mysqli_query($conn, $query) or die('error');

                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo '<option value="' . $row['BRAND'] . '">' . $row['BRAND'] . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
        </div>

    <div class="col-md-3" >
    <div class="form-group"><label control-label">choose COLLECTION</label>
        <select class="form-control" name="collection"> <!-- Updated name to 'collection' -->
            <option>select</option>
            <?php
                    $collection = []; // create an empty array to store unique brands

                    $query = "SELECT DISTINCT COLLECTION FROM sku_generator"; // Use DISTINCT to get unique brands
                    $result = mysqli_query($conn, $query) or die('error');

                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo '<option value="' . $row['COLLECTION'] . '">' . $row['COLLECTION'] . '</option>';
                        }
                    }
                    ?>


        </select>
    </div>
</div>


    <div class="col-md-3" >
    <div class="form-group"><label control-label">choose CATEGORY</label>
    <select class ="form-control" name="category">
    <option>select</option>
    <?php
                    $category = []; // create an empty array to store unique brands

                    $query = "SELECT DISTINCT CATEGORY FROM sku_generator"; // Use DISTINCT to get unique brands
                    $result = mysqli_query($conn, $query) or die('error');

                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo '<option value="' . $row['CATEGORY'] . '">' . $row['CATEGORY'] . '</option>';
                        }
                    }
                    ?>

</select></div>

    </div>


    <div class="col-md-3" >
    <div class="form-group"><label control-label">choose SUB-CATEGORY</label>
    <select class ="form-control" name="sub-category">
    <option>select</option>
    <?php
                    $subcategory = []; // create an empty array to store unique brands

                    $query = "SELECT DISTINCT SUB_CATEGORY FROM sku_generator"; // Use DISTINCT to get unique brands
                    $result = mysqli_query($conn, $query) or die('error');

                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo '<option value="' . $row['SUB_CATEGORY'] . '">' . $row['SUB_CATEGORY'] . '</option>';
                        }
                    }
                    ?>

</select></div>

    </div>
   
    </div>
    <div class="col-md" style="padding: 5px !important;margin-top: 54px;height: 20px;margin-left: 140px;">
            <button type="submit" name="submit" class="btn btn-primary">Submit</button>
        </div>
</form>

<?php
// Establish database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sku_generator";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the form is submitted
// Check if the form is submitted
if (isset($_POST['submit'])) {
    // Get the selected brand from the dropdown menu
    $selected_brand = $_POST['brands'];
    $selected_collection = $_POST['collection'];
    $selected_category = $_POST['category'];
    $selected_subcategory = $_POST['category'];
    

    // Construct the SQL query to select data based on the selected brand
    $sql = "SELECT * FROM sku_generator WHERE BRAND = '$selected_brand'";
    $sql = "SELECT * FROM sku_generator WHERE BRAND = '$selected_brand' AND COLLECTION = '$selected_collection' AND CATEGORY ='$selected_category'";
    
    
    // Execute the SQL query
    $result = $conn->query($sql);

    // Display the records in a table
    if ($result->num_rows > 0) {
        echo "<table border='1' >";
        echo "<tr><th>Brand</th><th>Collection</th><th>Category</th><th>Subcategory</th><th>Product Name</th><th>SKU</th><th>Title</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['BRAND'] . "</td>";
            echo "<td>" . $row['COLLECTION'] . "</td>";
            echo "<td>" . $row['CATEGORY'] . "</td>";
            echo "<td>" . $row['SUB_CATEGORY'] . "</td>";
            echo "<td>" . $row['PRODUCT_NAME'] . "</td>";
            echo "<td>" . $row['SKU'] . "</td>";
            echo "<td>" . $row['TITLE'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No records found for brand: " . $selected_brand;
    }
}


?>



      </div>

    </form>

   
</body>
</html>
<style>


    .col-md-3{
        margin-left:75px;
        padding:9px;
        border:1px solid #dbdbdb;
        background-color: #15740852;
        border-radius:15px;
    }
    .header {
        display:inline-flex;
        height:50px;
        width: 550px;
        margin-bottom: 25px;
        float:center;
        border-radius: 70px 0px 70px 0px;
        background-color: #0c2b0e75;
        color:white;
        margin-left: 30%;
        padding:20px;
    }
    html{
        background-image: url('https://cdn.shopify.com/s/files/1/0787/9258/9609/files/2_25cc185f-0ed1-45bd-8f8d-ce4fc6f9ffa6.png?v=1715237698');
  
       
    }

    table{
        background:#eef9ffd1;
    }
</style>





