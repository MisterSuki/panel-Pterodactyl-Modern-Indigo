import React from 'react';
import BoringAvatar, { AvatarProps } from 'boring-avatars';
import { useStoreState } from '@/state/hooks';
import { avatarFor } from '@/lib/avatar';

const palette = ['#FFAD08', '#EDD75A', '#73B06F', '#0C8F8F', '#587291'];

const _Avatar = ({ variant = 'beam', ...props }: AvatarProps) => (
    <BoringAvatar colors={palette} variant={variant} {...props} />
);

// The picture of the person who is signed in: the one they chose, or a gradient made from their name.
const _UserAvatar = () => {
    const avatar = useStoreState((state) => state.user.data?.avatar);
    const name = useStoreState((state) => state.user.data?.username || '');

    return <img src={avatarFor(avatar, name)} alt={''} className={'w-full h-full object-cover rounded-full'} />;
};

_Avatar.displayName = 'Avatar';
_UserAvatar.displayName = 'Avatar.User';

const Avatar = Object.assign(_Avatar, {
    User: _UserAvatar,
});

export default Avatar;
