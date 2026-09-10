export const canAssignRoles = (permissions: string[]): boolean => {
  return hasPermission(permissions, "roles.assign");
};

const hasPermission = (permissions: string[], permission: string): boolean => {
  return permissions.includes(permission);
};
