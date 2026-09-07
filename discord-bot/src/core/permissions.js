import { PermissionFlagsBits } from 'discord.js';

import { config } from './config.js';

/**
 * Menentukan apakah anggota berwenang menjalankan perintah sensitif.
 *
 * Wewenang diberikan bila anggota memegang role staff yang dikonfigurasi,
 * memiliki izin Manage Guild, atau merupakan pemilik server.
 */
export function isStaff(interaction) {
  const member = interaction.member;

  if (!member) {
    return false;
  }

  if (interaction.guild?.ownerId === member.id) {
    return true;
  }

  if (member.permissions?.has?.(PermissionFlagsBits.ManageGuild)) {
    return true;
  }

  const staffRole = config.roles.staff;

  if (staffRole === '') {
    return false;
  }

  const roles = member.roles?.cache;

  return Boolean(roles?.has?.(staffRole));
}

export function isModerator(interaction) {
  const member = interaction.member;

  if (!member) {
    return false;
  }

  if (isStaff(interaction)) {
    return true;
  }

  return Boolean(
    member.permissions?.has?.(PermissionFlagsBits.ModerateMembers)
    || member.permissions?.has?.(PermissionFlagsBits.KickMembers)
    || member.permissions?.has?.(PermissionFlagsBits.BanMembers),
  );
}

export const DENIED_MESSAGE = 'Perintah ini hanya untuk staff Younz Digital Center.';
