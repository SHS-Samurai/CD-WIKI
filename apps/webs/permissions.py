from .models import WEB_RIGHTS, Web


def user_can(user, web: Web, right: str) -> bool:
    return web.has_right(user, right)


def user_can_view(user, web: Web) -> bool:
    return user_can(user, web, "view")


def user_can_create(user, web: Web) -> bool:
    return user_can(user, web, "create")


def user_can_edit(user, web: Web) -> bool:
    return user_can(user, web, "edit")


def user_can_comment(user, web: Web) -> bool:
    return user_can(user, web, "comment")


def user_can_upload(user, web: Web) -> bool:
    return user_can(user, web, "upload")


def user_can_manage(user, web: Web) -> bool:
    return user_can(user, web, "manage")


def user_can_delete(user, web: Web) -> bool:
    return user_can(user, web, "delete")
