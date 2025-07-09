<?php
/**
 * reBB - Organization Management Tab Content
 *
 * This file provides the content for the "Organization Management" tab
 * in the user's profile section. It allows users to create or view
 * their organization details.
 */

// Ensure this file is accessed within the application context
// and $currentUser is available.
if (!isset($currentUser) || !$currentUser) {
    // Fallback or error if $currentUser is not set.
    // This might happen if the file is accessed directly or out of context.
    // Attempt to re-fetch if possible, or show an error.
    $reAuth = auth();
    if ($reAuth->isLoggedIn()) {
        $currentUser = $reAuth->getUser();
    } else {
        echo '<div class="alert alert-danger">User context not available. Please reload the page.</div>';
        return; // Stop further execution if user context is critical and unavailable
    }
}

$dbPath = ROOT_DIR . '/db';
$organization = null;
$userOrgLink = null;

try {
    // Initialize SleekDB store for user-organization links
    $userOrgStore = new \SleekDB\Store('user_to_organization_links', $dbPath, [
        'auto_cache' => false,
        'timeout' => false
    ]);

    // Check if the current user is linked to an organization
    $userOrgLink = $userOrgStore->findOneBy(['user_id', '=', $currentUser['_id']]);

    if ($userOrgLink && isset($userOrgLink['organization_id'])) {
        // User is linked to an organization, fetch its details
        $organizationsStore = new \SleekDB\Store('organizations', $dbPath, [
            'auto_cache' => false,
            'timeout' => false
        ]);
        $organization = $organizationsStore->findById($userOrgLink['organization_id']);
    }
} catch (\Exception $e) {
    // Log error or display a user-friendly message
    echo '<div class="alert alert-danger">Error accessing organization data: ' . htmlspecialchars($e->getMessage()) . '</div>';
    // Optionally, log $e->getMessage() to a file or error tracking system
}

?>

<div class="card">
    <div class="card-header">
        <h4 class="mb-0">Organization Details</h4>
    </div>
    <div class="card-body">
        <?php if ($organization): ?>
            <p>Welcome to your organization's management area.</p>
            <table class="table table-bordered">
                <tbody>
                    <tr>
                        <th scope="row" style="width: 200px;">Organization Name</th>
                        <td><?php echo htmlspecialchars($organization['organization_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Organization ID</th>
                        <td><?php echo htmlspecialchars($organization['_id'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Associated Form ID</th>
                        <td>
                            <?php if (isset($organization['form_id'])): ?>
                                <a href="<?php echo site_url('form?f=') . htmlspecialchars($organization['form_id']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($organization['form_id']); ?>
                                </a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Owner User ID</th>
                        <td><?php echo htmlspecialchars($organization['owner_user_id'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Created At</th>
                        <td><?php echo isset($organization['created_at']) ? date('Y-m-d H:i:s', $organization['created_at']) : 'N/A'; ?></td>
                    </tr>
                </tbody>
            </table>
            <p class="mt-3"><em>More organization management features will be available here in the future.</em></p>
        <?php elseif ($userOrgLink && !$organization): ?>
            <div class="alert alert-warning">
                You are linked to an organization, but its details could not be found. This might indicate a data inconsistency. Please contact support.
            </div>
        <?php else: ?>
            <div class="text-center p-4">
                <i class="bi bi-diagram-3" style="font-size: 3rem; color: #ccc;"></i>
                <p class="mt-3">You are not currently part of an organization.</p>
                <p>Create an organization to manage forms and collaborate with others.</p>
                <a href="<?php echo site_url('builder?type=organization'); ?>" class="btn btn-primary btn-lg">
                    <i class="bi bi-plus-circle"></i> Create Organization
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
