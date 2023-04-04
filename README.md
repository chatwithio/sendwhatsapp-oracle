# Send whatsapp with oracle as a backend

## index.php

This is a PHP script that processes data from an Oracle database and uses the WhatsApp360 API to send messages to patients on WhatsApp.

The script sets the error reporting level, memory limit, and execution time for PHP. It then defines a class CepferresWhatsapp with several methods.

The __construct method creates an instance of the WhatsApp360 class.

The connect method connects to an Oracle database and returns a connection object, and the closeConn method closes the connection.

The getRows method retrieves data from the database for patients who have an appointment scheduled for the next day, sorted by date and time. The method then returns the data as an array.

The updateRows method updates the data in the database with the status and status info from the WhatsApp360 API. It first creates a batch query that updates all rows in a single transaction, then executes the query and commits the transaction.

The run method is the main function of the script. It retrieves data from the database using the getRows method and iterates over each row. For each row, it extracts the necessary information and sends a message to the patient on WhatsApp using the WhatsApp360 API. It then updates the status and status info in the database using the updateRows method. Finally, it logs the success or failure of the message and updates the counters for the number of visits processed and the number of successful and unsuccessful messages sent.

## buttonlistener.php

This is a PHP script that defines a class ButtonEventListener which handles updating a database with information received from a web request. The database connection details are defined as constants inside the class, and there are private methods for connecting and disconnecting from the database, preparing the update query, and writing log messages.

The public method updateClient takes a base64-encoded client parameter from the web request, decodes it, and updates the database with the decoded client ID and some status information. The method returns true if the update is successful and false otherwise.

The script also defines two utility methods, base64url_encode and base64url_decode, for encoding and decoding data using base64 URL-safe characters.

Finally, the script checks for the presence of a client parameter in the web request, calls the updateClient method with it, and outputs an HTML message based on the success or failure of the update.

## WhatsApp360

This is a PHP class named WhatsApp360. It provides a basic starter code to send templated text-only messages via the 360-degree WhatsApp API.

The class contains several private properties, including endpoints and headers, which are used for API requests. It has a constructor method that sets the D360-API-KEY header.

The class contains several methods, including send, buildMessage, checkContact, buildParams, sendWhatsApp, getTemplates, and curlRequest.

send method accepts an endpoint and data array as arguments, performs a curl request and returns the response as a JSON object or throws an exception if there is an error.

buildMessage method accepts a message template and an array of placeholders as arguments, replaces each placeholder in the message template, and returns the resulting message.

checkContact method accepts a WhatsApp number as an argument, performs a blocking wait to validate the number, and returns a boolean.

buildParams method accepts an array of placeholders and returns an array of text-only parameters.

sendWhatsApp method accepts a WhatsApp number, an array of placeholders, a template name, a language, and a namespace as arguments. It calls the checkContact method to ensure that the number is valid, builds a payload, and sends a WhatsApp message via the 360-degree API.

getTemplates method returns an array of templates, namespaces, and approved statuses.

curlRequest method accepts a method, URL, and data array, and performs a curl request.


