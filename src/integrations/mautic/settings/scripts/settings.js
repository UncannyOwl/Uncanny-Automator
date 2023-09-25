/**
 * Resolves the Base URL, Username, and Password into one field.
 * 
 * @since 1.0.0
 */
class MauticSettingsResolver {

    getFieldValue(fieldId) {

        if (!document.getElementById(fieldId)) {
            throw 'Field #' + fieldId + ' is undefined.';
        }

        if (0 === document.getElementById(fieldId).value.length) {
            throw 'Field #' + fieldId + ' is empty';
        }

        return document.getElementById(fieldId).value;

    }

    resolve() {

        let credentials = new Object();

        try {

            credentials['baseUrl'] = this.getFieldValue('automator_mautic_base_url');
            credentials['userName'] = this.getFieldValue('automator_mautic_username');
            credentials['userPassword'] = this.getFieldValue('automator_mautic_password');

            if (!document.getElementById('automator_mautic_credentials')) {
                console.error('Mautic credentials field is missing.')
                return false;
            }

            document.getElementById('automator_mautic_credentials').value = JSON.stringify(credentials);

            return true;

        } catch (error) {

            console.error(error);
            return false;

        }

    }
}
