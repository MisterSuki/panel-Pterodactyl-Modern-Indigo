import http from '@/api/http';

// Asks the panel for a one-minute, single-use link that opens the database in phpMyAdmin, signed in.
export default (uuid: string, database: string): Promise<string> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/databases/${database}/phpmyadmin`)
            .then((response) => resolve(response.data.attributes.url))
            .catch(reject);
    });
};
