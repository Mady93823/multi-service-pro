/**
 * Route of the dashboard matching the user's highest-privilege role.
 * Mirrors App\Support\RoleRedirect on the backend.
 */
export function homeUrl(roles: string[]): string {
    if (roles.includes('admin')) {
        return '/admin/dashboard';
    }

    if (roles.includes('provider')) {
        return '/provider/dashboard';
    }

    return '/dashboard';
}
