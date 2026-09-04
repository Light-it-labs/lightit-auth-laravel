export const canAssignRoles = (permissions: string[]): boolean => {
  return hasPermission(permissions, "roles.assign");
};
