<?php
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');

if (isset($_POST['textBoxValue'])) {
    $output = '';
    $searchString = '%' . trim(htmlentities($_POST['textBoxValue'])) . '%';

    $sql = 'SELECT customerID, fullName FROM customer WHERE fullName LIKE ? OR customerID LIKE ? ORDER BY fullName ASC LIMIT 15';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$searchString, $searchString]);

    if ($stmt->rowCount() > 0) {
        $output = '<ul class="list-unstyled suggestionsList" id="creditCustomerIDSuggestionsList">';
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $output .= '<li data-customer-id="' . htmlspecialchars((string) $row['customerID']) . '">' . htmlspecialchars((string) $row['fullName']) . ' (ID: ' . htmlspecialchars((string) $row['customerID']) . ')</li>';
        }
        $output .= '</ul>';
    } else {
        $output = '';
    }
    $stmt->closeCursor();
    echo $output;
}
