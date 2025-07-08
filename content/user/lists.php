<?php
/**
 * reBB - User Form Lists Management
 * 
 * This file allows users to create and manage collections of forms.
 * Users must be logged in to access this page.
 */

// Require authentication before processing anything else
auth()->requireAuth('login'); // Ensures user is logged in
auth()->requireMinRole(Auth::ROLE_USER); // Ensures user is at least a 'user'

// Since we've passed the auth check, we can safely get the current user
$currentUser = auth()->getUser();

// Initialize variables
$actionMessage = '';
$actionMessageType = 'info';
$userLists = [];
$editingList = null;
$editingListId = null;
$editingListItems = [];

// Initialize the SleekDB store for form lists
$dbPath = ROOT_DIR . '/db';
$listStore = null;
$listItemStore = null;

try {
    // Make sure DB directory exists
    if (!is_dir($dbPath)) {
        mkdir($dbPath, 0755, true);
    }
    
    // Initialize stores
    $listStore = new \SleekDB\Store('form_lists', $dbPath, [
        'auto_cache' => false,
        'timeout' => false
    ]);
    
    $listItemStore = new \SleekDB\Store('form_list_items', $dbPath, [
        'auto_cache' => false,
        'timeout' => false
    ]);
    
    // Get all lists for this user
    $userLists = $listStore->findBy([
        ['user_id', '=', $currentUser['_id']]
    ], ['created_at' => 'desc']);
    
    // Count items in each list
    foreach ($userLists as &$list) {
        $listItems = $listItemStore->findBy([
            ['list_id', '=', $list['list_id']]
        ]);
        $list['item_count'] = count($listItems);
    }
    
} catch (\Exception $e) {
    $actionMessage = "Error loading lists: " . $e->getMessage();
    $actionMessageType = 'danger';
}

/**
 * Generate a unique list ID
 * 
 * @param int $length Length of the ID
 * @param \SleekDB\Store $store SleekDB store instance to check for existing IDs
 * @return string The generated ID
 */
function generateUniqueListId($length = 5, $store = null) {
    $attempts = 0;
    $maxAttempts = 10;
    
    while ($attempts < $maxAttempts) {
        // Generate a random string
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $id = '';
        for ($i = 0; $i < $length; $i++) {
            $id .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        // Check if this ID is already in use
        if ($store !== null) {
            try {
                $existingList = $store->findOneBy([
                    ['list_id', '=', $id]
                ]);
                
                if (!$existingList) {
                    return $id;
                }
            } catch (\Exception $e) {
                // If there's an error, just use the generated ID
                return $id;
            }
        } else {
            // If no store is provided, just return the generated ID
            return $id;
        }
        
        $attempts++;
    }
    
    // If we've tried too many times, add a timestamp to make it unique
    return substr(uniqid(), 0, $length);
}

// Handle list creation
if (isset($_POST['create_list'])) {
    $listName = trim($_POST['list_name'] ?? '');
    $listDescription = trim($_POST['list_description'] ?? '');
    $isPublic = isset($_POST['is_public']) && $_POST['is_public'] === 'on';
    
    if (empty($listName)) {
        $actionMessage = "List name is required.";
        $actionMessageType = 'danger';
    } else {
        try {
            // Generate a unique list ID
            $listId = generateUniqueListId(5, $listStore);
            
            // Create the list
            $newList = [
                'user_id' => $currentUser['_id'],
                'list_id' => $listId,
                'list_name' => $listName,
                'list_description' => $listDescription,
                'is_public' => $isPublic,
                'created_at' => time(),
                'updated_at' => time()
            ];
            
            $listStore->insert($newList);
            
            $actionMessage = "List created successfully.";
            $actionMessageType = 'success';
            
            // Redirect to edit the new list
            header("Location: lists?edit=" . $listId);
            exit;
        } catch (\Exception $e) {
            $actionMessage = "Error creating list: " . $e->getMessage();
            $actionMessageType = 'danger';
        }
    }
}

// Handle list deletion
if (isset($_POST['delete_list']) && isset($_POST['list_id'])) {
    $listId = $_POST['list_id'];
    
    try {
        // Find the list to ensure it belongs to the current user
        $list = $listStore->findOneBy([
            ['list_id', '=', $listId],
            'AND',
            ['user_id', '=', $currentUser['_id']]
        ]);
        
        if ($list) {
            // Delete all list items first
            $listItems = $listItemStore->findBy([
                ['list_id', '=', $listId]
            ]);
            
            foreach ($listItems as $item) {
                $listItemStore->deleteById($item['_id']);
            }
            
            // Now delete the list
            $listStore->deleteById($list['_id']);
            
            $actionMessage = "List deleted successfully.";
            $actionMessageType = 'success';
            
            // Reload the user's lists
            $userLists = $listStore->findBy([
                ['user_id', '=', $currentUser['_id']]
            ], ['created_at' => 'desc']);
            
            // Count items in each list
            foreach ($userLists as &$list) {
                $listItems = $listItemStore->findBy([
                    ['list_id', '=', $list['list_id']]
                ]);
                $list['item_count'] = count($listItems);
            }
        } else {
            $actionMessage = "List not found or doesn't belong to you.";
            $actionMessageType = 'danger';
        }
    } catch (\Exception $e) {
        $actionMessage = "Error deleting list: " . $e->getMessage();
        $actionMessageType = 'danger';
    }
}

// Handle list update
if (isset($_POST['update_list']) && isset($_POST['list_id'])) {
    $listId = $_POST['list_id'];
    $listName = trim($_POST['list_name'] ?? '');
    $listDescription = trim($_POST['list_description'] ?? '');
    $isPublic = isset($_POST['is_public']) && $_POST['is_public'] === 'on';
    
    if (empty($listName)) {
        $actionMessage = "List name is required.";
        $actionMessageType = 'danger';
    } else {
        try {
            // Find the list to ensure it belongs to the current user
            $list = $listStore->findOneBy([
                ['list_id', '=', $listId],
                'AND',
                ['user_id', '=', $currentUser['_id']]
            ]);
            
            if ($list) {
                // Update the list
                $listStore->updateById($list['_id'], [
                    'list_name' => $listName,
                    'list_description' => $listDescription,
                    'is_public' => $isPublic,
                    'updated_at' => time()
                ]);
                
                $actionMessage = "List updated successfully.";
                $actionMessageType = 'success';
                
                // Reload the user's lists
                $userLists = $listStore->findBy([
                    ['user_id', '=', $currentUser['_id']]
                ], ['created_at' => 'desc']);
                
                // Count items in each list
                foreach ($userLists as &$list) {
                    $listItems = $listItemStore->findBy([
                        ['list_id', '=', $list['list_id']]
                    ]);
                    $list['item_count'] = count($listItems);
                }
            } else {
                $actionMessage = "List not found or doesn't belong to you.";
                $actionMessageType = 'danger';
            }
        } catch (\Exception $e) {
            $actionMessage = "Error updating list: " . $e->getMessage();
            $actionMessageType = 'danger';
        }
    }
}

// Handle ajax form search
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search-forms' && isset($_GET['q'])) {
    header('Content-Type: application/json');
    
    $query = trim($_GET['q']);
    $results = [];
    
    if (!empty($query)) {
        try {
            // Search in user's forms and public forms
            $formsDir = STORAGE_DIR . '/forms';
            $forms = [];
            
            if (is_dir($formsDir)) {
                $files = scandir($formsDir);
                
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..' || !str_ends_with($file, '_schema.json')) {
                        continue;
                    }
                    
                    $formId = str_replace('_schema.json', '', $file);
                    $filePath = $formsDir . '/' . $file;
                    
                    if (is_readable($filePath)) {
                        $fileContent = file_get_contents($filePath);
                        $formData = json_decode($fileContent, true);
                        
                        if ($formData) {
                            $formName = isset($formData['formName']) ? $formData['formName'] : 'Unnamed Form';
                            
                            // Check if the form matches the search query
                            if (stripos($formName, $query) !== false || stripos($formId, $query) !== false) {
                                $forms[] = [
                                    'id' => $formId,
                                    'name' => $formName,
                                    'url' => site_url('form') . '?f=' . $formId
                                ];
                            }
                        }
                    }
                }
            }
            
            // Limit to 10 results
            $results = array_slice($forms, 0, 10);
        } catch (\Exception $e) {
            // Return empty results on error
        }
    }
    
    echo json_encode($results);
    exit;
}

// Handle adding form to list
if (isset($_POST['add_form_to_list']) && isset($_POST['list_id']) && isset($_POST['form_id'])) {
    $listId = $_POST['list_id'];
    $formId = $_POST['form_id'];
    $customTitle = trim($_POST['custom_title'] ?? '');
    $customDescription = trim($_POST['custom_description'] ?? '');
    
    try {
        // Find the list to ensure it belongs to the current user
        $list = $listStore->findOneBy([
            ['list_id', '=', $listId],
            'AND',
            ['user_id', '=', $currentUser['_id']]
        ]);
        
        if ($list) {
            // Check if form exists
            $formPath = STORAGE_DIR . '/forms/' . $formId . '_schema.json';
            if (!file_exists($formPath)) {
                $actionMessage = "Form not found.";
                $actionMessageType = 'danger';
            } else {
                // Check if the form is already in the list
                $existingItem = $listItemStore->findOneBy([
                    ['list_id', '=', $listId],
                    'AND',
                    ['form_id', '=', $formId]
                ]);
                
                if ($existingItem) {
                    $actionMessage = "This form is already in the list.";
                    $actionMessageType = 'warning';
                } else {
                    // Get form details
                    $formData = json_decode(file_get_contents($formPath), true);
                    $formName = isset($formData['formName']) ? $formData['formName'] : 'Unnamed Form';
                    
                    // Get the max display order
                    $listItems = $listItemStore->findBy([
                        ['list_id', '=', $listId]
                    ]);
                    $maxOrder = 0;
                    foreach ($listItems as $item) {
                        if (isset($item['display_order']) && $item['display_order'] > $maxOrder) {
                            $maxOrder = $item['display_order'];
                        }
                    }
                    
                    // Add the form to the list
                    $newItem = [
                        'list_id' => $listId,
                        'form_id' => $formId,
                        'form_name' => $formName, // Store the original form name
                        'custom_title' => $customTitle,
                        'custom_description' => $customDescription,
                        'display_order' => $maxOrder + 1,
                        'added_at' => time()
                    ];
                    
                    $listItemStore->insert($newItem);
                    
                    // Update the list's updated_at timestamp
                    $listStore->updateById($list['_id'], [
                        'updated_at' => time()
                    ]);
                    
                    $actionMessage = "Form added to list successfully.";
                    $actionMessageType = 'success';
                }
            }
        } else {
            $actionMessage = "List not found or doesn't belong to you.";
            $actionMessageType = 'danger';
        }
    } catch (\Exception $e) {
        $actionMessage = "Error adding form to list: " . $e->getMessage();
        $actionMessageType = 'danger';
    }
}

// Handle removing form from list
if (isset($_POST['remove_form_from_list']) && isset($_POST['list_id']) && isset($_POST['item_id'])) {
    $listId = $_POST['list_id'];
    $itemId = $_POST['item_id'];
    
    try {
        // Find the list to ensure it belongs to the current user
        $list = $listStore->findOneBy([
            ['list_id', '=', $listId],
            'AND',
            ['user_id', '=', $currentUser['_id']]
        ]);
        
        if ($list) {
            // Find the item
            $item = $listItemStore->findById($itemId);
            
            if ($item && $item['list_id'] === $listId) {
                // Delete the item
                $listItemStore->deleteById($itemId);
                
                // Update the list's updated_at timestamp
                $listStore->updateById($list['_id'], [
                    'updated_at' => time()
                ]);
                
                $actionMessage = "Form removed from list successfully.";
                $actionMessageType = 'success';
            } else {
                $actionMessage = "Form not found in list.";
                $actionMessageType = 'danger';
            }
        } else {
            $actionMessage = "List not found or doesn't belong to you.";
            $actionMessageType = 'danger';
        }
    } catch (\Exception $e) {
        $actionMessage = "Error removing form from list: " . $e->getMessage();
        $actionMessageType = 'danger';
    }
}

// Handle updating form in list
if (isset($_POST['update_form_in_list']) && isset($_POST['list_id']) && isset($_POST['item_id'])) {
    $listId = $_POST['list_id'];
    $itemId = $_POST['item_id'];
    $customTitle = trim($_POST['custom_title'] ?? '');
    $customDescription = trim($_POST['custom_description'] ?? '');
    
    try {
        // Find the list to ensure it belongs to the current user
        $list = $listStore->findOneBy([
            ['list_id', '=', $listId],
            'AND',
            ['user_id', '=', $currentUser['_id']]
        ]);
        
        if ($list) {
            // Find the item
            $item = $listItemStore->findById($itemId);
            
            if ($item && $item['list_id'] === $listId) {
                // Update the item
                $listItemStore->updateById($itemId, [
                    'custom_title' => $customTitle,
                    'custom_description' => $customDescription
                ]);
                
                // Update the list's updated_at timestamp
                $listStore->updateById($list['_id'], [
                    'updated_at' => time()
                ]);
                
                $actionMessage = "Form information updated successfully.";
                $actionMessageType = 'success';
            } else {
                $actionMessage = "Form not found in list.";
                $actionMessageType = 'danger';
            }
        } else {
            $actionMessage = "List not found or doesn't belong to you.";
            $actionMessageType = 'danger';
        }
    } catch (\Exception $e) {
        $actionMessage = "Error updating form in list: " . $e->getMessage();
        $actionMessageType = 'danger';
    }
}

// Handle reordering form items
if (isset($_POST['reorder_forms']) && isset($_POST['list_id']) && isset($_POST['item_order'])) {
    $listId = $_POST['list_id'];
    $itemOrder = json_decode($_POST['item_order'], true);
    
    if (!is_array($itemOrder)) {
        $actionMessage = "Invalid order data.";
        $actionMessageType = 'danger';
    } else {
        try {
            // Find the list to ensure it belongs to the current user
            $list = $listStore->findOneBy([
                ['list_id', '=', $listId],
                'AND',
                ['user_id', '=', $currentUser['_id']]
            ]);
            
            if ($list) {
                // Update each item's display order
                foreach ($itemOrder as $index => $itemId) {
                    $item = $listItemStore->findById($itemId);
                    
                    if ($item && $item['list_id'] === $listId) {
                        $listItemStore->updateById($itemId, [
                            'display_order' => $index + 1
                        ]);
                    }
                }
                
                // Update the list's updated_at timestamp
                $listStore->updateById($list['_id'], [
                    'updated_at' => time()
                ]);
                
                $actionMessage = "Form order updated successfully.";
                $actionMessageType = 'success';
            } else {
                $actionMessage = "List not found or doesn't belong to you.";
                $actionMessageType = 'danger';
            }
        } catch (\Exception $e) {
            $actionMessage = "Error updating form order: " . $e->getMessage();
            $actionMessageType = 'danger';
        }
    }
}

// Check if we're in edit mode
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $editingListId = $_GET['edit'];
    
    try {
        // Find the list to ensure it belongs to the current user
        $editingList = $listStore->findOneBy([
            ['list_id', '=', $editingListId],
            'AND',
            ['user_id', '=', $currentUser['_id']]
        ]);
        
        if ($editingList) {
            // Get the list items
            $editingListItems = $listItemStore->findBy([
                ['list_id', '=', $editingListId]
            ], ['display_order' => 'asc']);

            // Load form information for each item - FIXED VERSION
            // Change from foreach ($editingListItems as &$item) to use an index
            foreach ($editingListItems as $key => $item) {
                $formPath = STORAGE_DIR . '/forms/' . $item['form_id'] . '_schema.json';
                
                if (file_exists($formPath)) {
                    $formData = json_decode(file_get_contents($formPath), true);
                    $editingListItems[$key]['form_name'] = isset($formData['formName']) ? $formData['formName'] : 'Unnamed Form';
                } else {
                    $editingListItems[$key]['form_name'] = 'Unknown Form';
                    $editingListItems[$key]['not_found'] = true;
                }
            }
        } else {
            $actionMessage = "List not found or doesn't belong to you.";
            $actionMessageType = 'danger';
            $editingListId = null;
        }
    } catch (\Exception $e) {
        $actionMessage = "Error loading list: " . $e->getMessage();
        $actionMessageType = 'danger';
        $editingListId = null;
    }
}

// Define the page content to be yielded in the master layout
ob_start();
?>

<div class="container-admin">
    <div class="page-header">
        <h1><?php echo $editingList ? 'Edit List: ' . htmlspecialchars($editingList['list_name']) : 'My Form Lists'; ?></h1>
        <div>
            <span class="text-muted me-3">Welcome, <?php echo htmlspecialchars($currentUser['username']); ?></span>
            <a href="<?php echo site_url('profile'); ?>" class="btn btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Back to Profile
            </a>
            <?php if ($editingList): ?>
            <a href="<?php echo site_url('lists'); ?>" class="btn btn-outline-primary me-2">
                <i class="bi bi-list-ul"></i> All Lists
            </a>
            <a href="<?php echo site_url('list') . '?l=' . $editingListId; ?>" class="btn btn-outline-success me-2" target="_blank">
                <i class="bi bi-eye"></i> View List
            </a>
            <?php endif; ?>
            <a href="<?php echo site_url('logout'); ?>" class="btn btn-outline-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
    
    <?php if ($actionMessage): ?>
        <div class="alert alert-<?php echo $actionMessageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($actionMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($editingList): ?>
    <!-- Edit List Mode -->
    <div class="row">
        <!-- Left column - List details -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">List Details</h4>
                </div>
                <div class="card-body">
                    <form method="post" action="lists?edit=<?php echo htmlspecialchars($editingListId); ?>">
                        <input type="hidden" name="list_id" value="<?php echo htmlspecialchars($editingListId); ?>">
                        
                        <div class="mb-3">
                            <label for="list_name" class="form-label">List Name</label>
                            <input type="text" class="form-control" id="list_name" name="list_name" 
                                   value="<?php echo htmlspecialchars($editingList['list_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="list_description" class="form-label">Description</label>
                            <textarea class="form-control" id="list_description" name="list_description" rows="3"><?php echo htmlspecialchars($editingList['list_description']); ?></textarea>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_public" name="is_public" 
                                   <?php echo isset($editingList['is_public']) && $editingList['is_public'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_public">Public List</label>
                            <small class="form-text text-muted d-block">If checked, anyone with the link can view this list</small>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="update_list" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                            
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteListModal">
                                <i class="bi bi-trash"></i> Delete List
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h4 class="mb-0">List Information</h4>
                </div>
                <div class="card-body">
                    <p><strong>List ID:</strong> <?php echo htmlspecialchars($editingListId); ?></p>
                    <p><strong>Created:</strong> <?php echo date('Y-m-d H:i', $editingList['created_at']); ?></p>
                    <p><strong>Last Updated:</strong> <?php echo date('Y-m-d H:i', $editingList['updated_at']); ?></p>
                    <p><strong>Form Count:</strong> <?php echo count($editingListItems); ?></p>
                    
                    <div class="mt-3">
                        <h5>Share List</h5>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" id="list-share-url" 
                                   value="<?php echo site_url('list') . '?l=' . htmlspecialchars($editingListId); ?>" readonly>
                            <button class="btn btn-outline-primary" type="button" id="copy-list-url">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                        <small class="text-muted">Copy this link to share your list with others</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right column - Forms in list -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Forms in This List</h4>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addFormModal">
                        <i class="bi bi-plus-circle"></i> Add Form
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($editingListItems)): ?>
                        <div class="text-center my-5">
                            <i class="bi bi-clipboard-plus" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3">This list doesn't have any forms yet.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFormModal">
                                <i class="bi bi-plus-circle"></i> Add Your First Form
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="list-form-items" id="sortable-list-items">
                            <?php foreach ($editingListItems as $item): ?>
                                <div class="list-form-item card mb-3" data-id="<?php echo $item['_id']; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div class="list-form-info">
                                                <h5 class="mb-1">
                                                    <?php echo htmlspecialchars($item['custom_title'] ?: $item['form_name']); ?>
                                                    <span class="drag-handle ms-2"><i class="bi bi-grip-vertical"></i></span>
                                                </h5>
                                                <?php if (!empty($item['custom_description'])): ?>
                                                    <p class="mb-1"><?php echo htmlspecialchars($item['custom_description']); ?></p>
                                                <?php endif; ?>
                                                <small class="text-muted">Form ID: <?php echo htmlspecialchars($item['form_id']); ?></small>
                                            </div>
                                            <div class="list-form-actions">
                                                <a href="<?php echo site_url('form') . '?f=' . htmlspecialchars($item['form_id']); ?>" class="btn btn-sm btn-outline-primary me-1" target="_blank">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-secondary me-1 edit-form-btn" 
                                                        data-id="<?php echo $item['_id']; ?>"
                                                        data-form-id="<?php echo htmlspecialchars($item['form_id']); ?>"
                                                        data-form-name="<?php echo htmlspecialchars($item['form_name']); ?>"
                                                        data-custom-title="<?php echo htmlspecialchars($item['custom_title'] ?? ''); ?>"
                                                        data-custom-description="<?php echo htmlspecialchars($item['custom_description'] ?? ''); ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-form-btn" 
                                                        data-id="<?php echo $item['_id']; ?>"
                                                        data-form-name="<?php echo htmlspecialchars($item['custom_title'] ?: $item['form_name']); ?>">
                                                    <i class="bi bi-trash"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <form id="reorder-form" method="post" action="lists?edit=<?php echo htmlspecialchars($editingListId); ?>">
                            <input type="hidden" name="list_id" value="<?php echo htmlspecialchars($editingListId); ?>">
                            <input type="hidden" name="item_order" id="item-order-input">
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Form Modal -->
    <div class="modal fade" id="addFormModal" tabindex="-1" aria-labelledby="addFormModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addFormModalLabel">Add Form to List</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="lists?edit=<?php echo htmlspecialchars($editingListId); ?>" id="add-form-form">
                        <input type="hidden" name="list_id" value="<?php echo htmlspecialchars($editingListId); ?>">
                        
                        <div class="mb-3">
                            <label for="form_id" class="form-label">Form ID or URL</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="form_id" name="form_id" required>
                                <button class="btn btn-outline-secondary" type="button" id="search-form-btn">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Enter a form ID or URL to add it to this list</small>
                        </div>
                        
                        <div class="mb-3 d-none" id="form-search-results">
                            <label class="form-label">Search Results</label>
                            <div class="list-group" id="form-results-list">
                                <!-- Search results will be inserted here -->
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="custom_title" class="form-label">Custom Title (Optional)</label>
                            <input type="text" class="form-control" id="custom_title" name="custom_title">
                            <small class="form-text text-muted">Leave blank to use the original form name</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="custom_description" class="form-label">Custom Description (Optional)</label>
                            <textarea class="form-control" id="custom_description" name="custom_description" rows="3"></textarea>
                            <small class="form-text text-muted">Add a description for this form in your list</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="add-form-form" name="add_form_to_list" class="btn btn-primary">Add Form</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Form Modal -->
    <div class="modal fade" id="editFormModal" tabindex="-1" aria-labelledby="editFormModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editFormModalLabel">Edit Form Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="lists?edit=<?php echo htmlspecialchars($editingListId); ?>" id="edit-form-form">
                        <input type="hidden" name="list_id" value="<?php echo htmlspecialchars($editingListId); ?>">
                        <input type="hidden" name="item_id" id="edit_item_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Original Form Name</label>
                            <input type="text" class="form-control" id="edit_form_name" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_custom_title" class="form-label">Custom Title</label>
                            <input type="text" class="form-control" id="edit_custom_title" name="custom_title">
                            <small class="form-text text-muted">Leave blank to use the original form name</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_custom_description" class="form-label">Custom Description</label>
                            <textarea class="form-control" id="edit_custom_description" name="custom_description" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="edit-form-form" name="update_form_in_list" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Remove Form Confirmation Modal -->
    <div class="modal fade" id="removeFormModal" tabindex="-1" aria-labelledby="removeFormModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="removeFormModalLabel">Confirm Removal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to remove "<span id="remove-form-name"></span>" from this list?
                    <p class="mt-2">This will only remove it from the list, not delete the form itself.</p>
                </div>
                <div class="modal-footer">
                    <form method="post" action="lists?edit=<?php echo htmlspecialchars($editingListId); ?>">
                        <input type="hidden" name="list_id" value="<?php echo htmlspecialchars($editingListId); ?>">
                        <input type="hidden" name="item_id" id="remove_item_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="remove_form_from_list" class="btn btn-danger">Remove</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete List Confirmation Modal -->
    <div class="modal fade" id="deleteListModal" tabindex="-1" aria-labelledby="deleteListModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteListModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete the list "<?php echo htmlspecialchars($editingList['list_name']); ?>"?
                    <p class="text-danger mt-2">This action cannot be undone and will remove all forms from this list.</p>
                </div>
                <div class="modal-footer">
                    <form method="post" action="lists">
                        <input type="hidden" name="list_id" value="<?php echo htmlspecialchars($editingListId); ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_list" class="btn btn-danger">Delete List</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form search functionality
            const searchFormBtn = document.getElementById('search-form-btn');
            const formIdInput = document.getElementById('form_id');
            const searchResults = document.getElementById('form-search-results');
            const resultsList = document.getElementById('form-results-list');
            
            if (searchFormBtn) {
                searchFormBtn.addEventListener('click', function() {
                    const query = formIdInput.value.trim();
                    
                    if (query.length < 2) {
                        searchResults.classList.add('d-none');
                        return;
                    }
                    
                    // Extract form ID from URL if needed
                    let formId = query;
                    if (query.includes('?f=')) {
                        const urlParams = new URLSearchParams(query.split('?')[1]);
                        formId = urlParams.get('f') || query;
                    }
                    
                    // If it's a valid form ID, just use it
                    if (/^[a-z0-9]+$/.test(formId) && formId.length >= 3) {
                        formIdInput.value = formId;
                        searchResults.classList.add('d-none');
                        return;
                    }
                    
                    // Otherwise, search for forms
                    fetch(`lists?ajax=search-forms&q=${encodeURIComponent(query)}`)
                        .then(response => response.json())
                        .then(data => {
                            resultsList.innerHTML = '';
                            
                            if (data.length === 0) {
                                resultsList.innerHTML = '<div class="list-group-item">No forms found</div>';
                            } else {
                                data.forEach(form => {
                                    const item = document.createElement('button');
                                    item.type = 'button';
                                    item.className = 'list-group-item list-group-item-action';
                                    item.innerHTML = `
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>${form.name}</strong><br>
                                                <small class="text-muted">ID: ${form.id}</small>
                                            </div>
                                            <span class="badge bg-primary">Select</span>
                                        </div>
                                    `;
                                    
                                    item.addEventListener('click', function() {
                                        formIdInput.value = form.id;
                                        searchResults.classList.add('d-none');
                                    });
                                    
                                    resultsList.appendChild(item);
                                });
                            }
                            
                            searchResults.classList.remove('d-none');
                        })
                        .catch(error => {
                            console.error('Error searching for forms:', error);
                            searchResults.classList.add('d-none');
                        });
                });
                
                // Search on enter key
                formIdInput.addEventListener('keyup', function(e) {
                    if (e.key === 'Enter') {
                        searchFormBtn.click();
                    }
                });
            }
            
            // Copy list URL
            const copyListUrlBtn = document.getElementById('copy-list-url');
            const listShareUrl = document.getElementById('list-share-url');
            
            if (copyListUrlBtn && listShareUrl) {
                copyListUrlBtn.addEventListener('click', function() {
                    listShareUrl.select();
                    document.execCommand('copy');
                    
                    const originalText = copyListUrlBtn.innerHTML;
                    copyListUrlBtn.innerHTML = '<i class="bi bi-check"></i>';
                    
                    setTimeout(function() {
                        copyListUrlBtn.innerHTML = originalText;
                    }, 2000);
                });
            }
            
            // Edit form modal
            const editButtons = document.querySelectorAll('.edit-form-btn');
            
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-id');
                    const formName = this.getAttribute('data-form-name');
                    const customTitle = this.getAttribute('data-custom-title');
                    const customDescription = this.getAttribute('data-custom-description');
                    
                    document.getElementById('edit_item_id').value = itemId;
                    document.getElementById('edit_form_name').value = formName;
                    document.getElementById('edit_custom_title').value = customTitle;
                    document.getElementById('edit_custom_description').value = customDescription;
                    
                    const editFormModal = new bootstrap.Modal(document.getElementById('editFormModal'));
                    editFormModal.show();
                });
            });
            
            // Remove form confirmation
            const removeButtons = document.querySelectorAll('.remove-form-btn');
            
            removeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-id');
                    const formName = this.getAttribute('data-form-name');
                    
                    document.getElementById('remove_item_id').value = itemId;
                    document.getElementById('remove-form-name').textContent = formName;
                    
                    const removeFormModal = new bootstrap.Modal(document.getElementById('removeFormModal'));
                    removeFormModal.show();
                });
            });
            
            // Sortable list
            const sortableList = document.getElementById('sortable-list-items');
            let sortable;
            
            if (sortableList && typeof Sortable !== 'undefined') {
                sortable = Sortable.create(sortableList, {
                    handle: '.drag-handle',
                    animation: 150,
                    onEnd: function() {
                        // Get the new order of items
                        const items = sortable.toArray();
                        
                        // Update the hidden input field
                        document.getElementById('item-order-input').value = JSON.stringify(items);
                        
                        // Submit the form
                        document.getElementById('reorder-form').submit();
                    }
                });
            }
        });
    </script>
    
    <?php else: ?>
    <!-- List Management Mode -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">My Form Lists</h4>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#createListModal">
                        <i class="bi bi-plus-circle"></i> Create New List
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($userLists)): ?>
                        <div class="text-center my-5">
                            <i class="bi bi-list-ul" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3">You haven't created any form lists yet.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createListModal">
                                <i class="bi bi-plus-circle"></i> Create Your First List
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="form-lists">
                            <?php foreach ($userLists as $list): ?>
                                <div class="list-item card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div class="list-info">
                                                <h5 class="mb-1">
                                                    <?php echo htmlspecialchars($list['list_name']); ?>
                                                    <?php if (isset($list['is_public']) && $list['is_public']): ?>
                                                        <span class="badge bg-success ms-2">Public</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary ms-2">Private</span>
                                                    <?php endif; ?>
                                                </h5>
                                                <?php if (!empty($list['list_description'])): ?>
                                                    <p class="mb-1"><?php echo htmlspecialchars($list['list_description']); ?></p>
                                                <?php endif; ?>
                                                <small class="text-muted">
                                                    <?php echo $list['item_count']; ?> form<?php echo $list['item_count'] !== 1 ? 's' : ''; ?> • 
                                                    Created: <?php echo date('Y-m-d', $list['created_at']); ?> • 
                                                    Last updated: <?php echo date('Y-m-d', $list['updated_at']); ?>
                                                </small>
                                            </div>
                                            <div class="list-actions">
                                                <a href="<?php echo site_url('list') . '?l=' . htmlspecialchars($list['list_id']); ?>" class="btn btn-sm btn-outline-primary me-1" target="_blank">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                                <a href="<?php echo site_url('lists') . '?edit=' . htmlspecialchars($list['list_id']); ?>" class="btn btn-sm btn-outline-secondary me-1">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-list-btn" 
                                                        data-id="<?php echo htmlspecialchars($list['list_id']); ?>"
                                                        data-name="<?php echo htmlspecialchars($list['list_name']); ?>">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0">About Form Lists</h4>
                </div>
                <div class="card-body">
                    <p>Form Lists allow you to organize and share multiple forms in one place.</p>
                    
                    <h5 class="mt-4">With Form Lists, you can:</h5>
                    <ul>
                        <li>Create collections of related forms</li>
                        <li>Add custom titles and descriptions</li>
                        <li>Organize forms in any order</li>
                        <li>Share multiple forms with a single link</li>
                        <li>Add any public form, even ones you didn't create</li>
                    </ul>
                    
                    <div class="mt-4">
                        <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#createListModal">
                            <i class="bi bi-plus-circle"></i> Create New List
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create List Modal -->
    <div class="modal fade" id="createListModal" tabindex="-1" aria-labelledby="createListModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createListModalLabel">Create New Form List</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="lists" id="create-list-form">
                        <div class="mb-3">
                            <label for="list_name" class="form-label">List Name</label>
                            <input type="text" class="form-control" id="list_name" name="list_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="list_description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="list_description" name="list_description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_public" name="is_public" checked>
                            <label class="form-check-label" for="is_public">Public List</label>
                            <small class="form-text text-muted d-block">If checked, anyone with the link can view this list</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="create-list-form" name="create_list" class="btn btn-primary">Create List</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete List Confirmation Modal -->
    <div class="modal fade" id="deleteListModal" tabindex="-1" aria-labelledby="deleteListModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteListModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete the list "<span id="list-name-to-delete"></span>"?
                    <p class="text-danger mt-2">This action cannot be undone and will remove all forms from this list.</p>
                </div>
                <div class="modal-footer">
                    <form method="post" action="lists">
                        <input type="hidden" name="list_id" id="list-id-to-delete">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_list" class="btn btn-danger">Delete List</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Delete list confirmation
            const deleteButtons = document.querySelectorAll('.delete-list-btn');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const listId = this.getAttribute('data-id');
                    const listName = this.getAttribute('data-name');
                    
                    document.getElementById('list-id-to-delete').value = listId;
                    document.getElementById('list-name-to-delete').textContent = listName;
                    
                    const deleteListModal = new bootstrap.Modal(document.getElementById('deleteListModal'));
                    deleteListModal.show();
                });
            });
        });
    </script>
    <?php endif; ?>
</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = $editingList ? 'Edit List: ' . $editingList['list_name'] : 'My Form Lists';

// Add page-specific CSS
$GLOBALS['page_css'] = '
<link rel="stylesheet" href="'. asset_path('css/pages/admin.css') .'?v=' . APP_VERSION . '">
<link rel="stylesheet" href="'. asset_path('css/pages/list.css') .'?v=' . APP_VERSION . '">
';

// Add Sortable.js for drag-and-drop functionality
$GLOBALS['page_javascript'] = '
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';