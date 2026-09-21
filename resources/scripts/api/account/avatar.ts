import http from '@/api/http';

// Sends the picture chosen by the person and gives back where it can now be found.
export const uploadAvatar = async (file: File): Promise<string> => {
    const body = new FormData();
    body.append('avatar', file);

    const { data } = await http.post('/api/client/account/avatar', body, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    return data.avatar;
};

// Goes back to the default picture, and gives back where it is.
export const removeAvatar = async (): Promise<string> => {
    const { data } = await http.delete('/api/client/account/avatar');

    return data.avatar;
};
