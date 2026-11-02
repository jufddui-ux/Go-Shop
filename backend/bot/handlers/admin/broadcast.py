from datetime import datetime
from typing import Optional

from aiogram import Router, F
from aiogram.types import CallbackQuery, Message, InlineKeyboardMarkup, InlineKeyboardButton
from aiogram.fsm.context import FSMContext
from aiogram.exceptions import TelegramBadRequest, TelegramForbiddenError

from bot.i18n import localize
from bot.database.models import Permission
from bot.database.methods import get_all_users
from bot.keyboards import back, close, simple_buttons
from bot.database.methods.audit import log_audit
from bot.filters import HasPermissionFilter
from bot.handlers.other import caller_name
from bot.misc import BroadcastMessage, sanitize_html
from bot.misc.services import BroadcastManager, BroadcastStats
from bot.states import BroadcastFSM

router = Router()

broadcast_managers: dict[int, Optional[BroadcastManager]] = {}


def _cancel_keyboard():
    """Progress-message keyboard with a working cancel button."""
    return simple_buttons([(localize("broadcast.btn.cancel"), "cancel_broadcast")])


def _button_choice_keyboard() -> InlineKeyboardMarkup:
    """Keyboard for selecting which button to attach to the broadcast."""
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text="🛒 Menu / Shop Button", callback_data="bc_btn:menu")],
        [InlineKeyboardButton(text="🔗 Custom Link Button", callback_data="bc_btn:custom")],
        [InlineKeyboardButton(text="🚫 No Button", callback_data="bc_btn:none")],
        [InlineKeyboardButton(text="❌ Cancel", callback_data="cancel_broadcast_setup")]
    ])


def _confirm_broadcast_keyboard() -> InlineKeyboardMarkup:
    """Keyboard for confirming broadcast delivery."""
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text="🚀 Send to All Users", callback_data="bc_confirm:send")],
        [InlineKeyboardButton(text="❌ Cancel", callback_data="cancel_broadcast_setup")]
    ])


def _build_broadcast_markup(btn_type: str | None, btn_text: str | None = None, btn_url: str | None = None) -> Optional[InlineKeyboardMarkup]:
    """Construct reply_markup for the broadcast."""
    if btn_type == "menu":
        return InlineKeyboardMarkup(inline_keyboard=[
            [InlineKeyboardButton(text=btn_text or "🛍 Open Shop / Menu", callback_data="back_to_menu")]
        ])
    if btn_type == "custom" and btn_text and btn_url:
        return InlineKeyboardMarkup(inline_keyboard=[
            [InlineKeyboardButton(text=btn_text, url=btn_url)]
        ])
    return None


async def _show_preview_and_confirm(message: Message, state: FSMContext):
    """Show the admin a preview of the broadcast message and the confirmation prompt."""
    data = await state.get_data()
    text = data.get("text", "")
    photo = data.get("photo")
    btn_type = data.get("btn_type")
    btn_text = data.get("btn_text")
    btn_url = data.get("btn_url")

    markup = _build_broadcast_markup(btn_type, btn_text, btn_url)

    if photo:
        await message.answer_photo(
            photo=photo,
            caption=f"👁 <b>[BROADCAST PREVIEW]</b>\n\n{text}" if text else "👁 <b>[BROADCAST PREVIEW]</b>",
            reply_markup=markup,
            parse_mode="HTML"
        )
    else:
        await message.answer(
            text=f"👁 <b>[BROADCAST PREVIEW]</b>\n\n{text}",
            reply_markup=markup,
            parse_mode="HTML"
        )

    await message.answer(
        "⬆️ <b>Upar broadcast ka preview hai.</b>\n\nKya aap yeh message sabhi users ko bhejna chahte hain?",
        reply_markup=_confirm_broadcast_keyboard(),
        parse_mode="HTML"
    )
    await state.set_state(BroadcastFSM.waiting_confirmation)


@router.callback_query(F.data == "send_message", HasPermissionFilter(permission=Permission.BROADCAST))
async def send_message_callback_handler(call: CallbackQuery, state: FSMContext):
    """Beginning of mailing"""
    await call.message.edit_text(
        "📢 <b>Broadcast / Mailing</b>\n\n"
        "Aap jo message bhejna chahte hain, wo bhejein:\n"
        "• Sirf <b>Text message</b> bhej sakte hain\n"
        "• Ya <b>Photo (Image) with Caption</b> bhej sakte hain\n\n"
        "<i>Agli screen par aap button bhi add kar sakenge.</i>",
        reply_markup=back("console"),
        parse_mode="HTML"
    )
    await state.set_state(BroadcastFSM.waiting_message)


@router.message(BroadcastFSM.waiting_message, F.photo)
async def broadcast_photo_received(message: Message, state: FSMContext):
    """Handle broadcast photo with optional caption"""
    photo_id = message.photo[-1].file_id
    raw_text = message.caption or ""
    safe_text = sanitize_html(raw_text)

    await state.update_data(photo=photo_id, text=safe_text)
    await message.answer(
        "📸 <b>Image receive ho gayi!</b>\n\n"
        "Kya aap is message ke sath koi <b>Button</b> lagana chahte hain?",
        reply_markup=_button_choice_keyboard(),
        parse_mode="HTML"
    )


@router.message(BroadcastFSM.waiting_message, F.text)
async def broadcast_text_received(message: Message, state: FSMContext):
    """Handle broadcast text message"""
    safe_text = sanitize_html(message.text)

    await state.update_data(photo=None, text=safe_text)
    await message.answer(
        "📝 <b>Text receive ho gaya!</b>\n\n"
        "Kya aap is message ke sath koi <b>Button</b> lagana chahte hain?",
        reply_markup=_button_choice_keyboard(),
        parse_mode="HTML"
    )


@router.callback_query(F.data == "bc_btn:menu", BroadcastFSM.waiting_message)
async def broadcast_btn_menu(call: CallbackQuery, state: FSMContext):
    """Attach main menu button to broadcast"""
    await state.update_data(btn_type="menu", btn_text="🛍 Open Shop / Menu")
    await call.message.delete()
    await _show_preview_and_confirm(call.message, state)


@router.callback_query(F.data == "bc_btn:none", BroadcastFSM.waiting_message)
async def broadcast_btn_none(call: CallbackQuery, state: FSMContext):
    """No button on broadcast"""
    await state.update_data(btn_type=None)
    await call.message.delete()
    await _show_preview_and_confirm(call.message, state)


@router.callback_query(F.data == "bc_btn:custom", BroadcastFSM.waiting_message)
async def broadcast_btn_custom_prompt(call: CallbackQuery, state: FSMContext):
    """Prompt for custom button text and url"""
    await call.message.edit_text(
        "🔗 <b>Custom Button Add Karein:</b>\n\n"
        "Button ka naam aur link is format mein bhejein:\n"
        "<code>Button Name | https://example.com</code>\n\n"
        "<i>Misaal: Visit Channel | https://t.me/mychannel</i>",
        reply_markup=simple_buttons([("❌ Cancel", "cancel_broadcast_setup")]),
        parse_mode="HTML"
    )
    await state.set_state(BroadcastFSM.waiting_custom_button)


@router.message(BroadcastFSM.waiting_custom_button, F.text)
async def broadcast_custom_button_received(message: Message, state: FSMContext):
    """Process custom button text and URL"""
    text = message.text or ""
    if "|" not in text:
        await message.answer(
            "⚠️ Format sahi nahi hai. Baraye meherbani is tarah likhein:\n"
            "<code>Button Name | https://example.com</code>",
            parse_mode="HTML"
        )
        return

    parts = text.split("|", 1)
    btn_text = parts[0].strip()
    btn_url = parts[1].strip()

    if not btn_url.startswith(("http://", "https://", "tg://")):
        await message.answer("⚠️ Link <code>https://</code> ya <code>http://</code> ya <code>tg://</code> se start hona chahiye.", parse_mode="HTML")
        return

    await state.update_data(btn_type="custom", btn_text=btn_text, btn_url=btn_url)
    await _show_preview_and_confirm(message, state)


@router.callback_query(F.data == "cancel_broadcast_setup")
async def cancel_broadcast_setup_handler(call: CallbackQuery, state: FSMContext):
    """Cancel broadcast creation before sending"""
    await state.clear()
    await call.message.edit_text("❌ Broadcast cancel kar di gayi.", reply_markup=back("console"))


@router.callback_query(F.data == "bc_confirm:send", BroadcastFSM.waiting_confirmation)
async def execute_confirmed_broadcast(call: CallbackQuery, state: FSMContext):
    """Executing confirmed broadcast mailing with progress bar"""
    admin_id = call.from_user.id

    if admin_id in broadcast_managers:
        await call.answer(localize("broadcast.already_running"), show_alert=True)
        return
    broadcast_managers[admin_id] = None

    data = await state.get_data()
    safe_text = data.get("text", "")
    photo = data.get("photo")
    btn_type = data.get("btn_type")
    btn_text = data.get("btn_text")
    btn_url = data.get("btn_url")
    markup = _build_broadcast_markup(btn_type, btn_text, btn_url)

    try:
        users = await get_all_users()
        user_ids = [int(row[0]) for row in users]

        await call.message.delete()

        progress_msg = await call.message.answer(
            localize("broadcast.creating", ids=len(user_ids)),
            reply_markup=_cancel_keyboard()
        )

        async def update_progress(stats: BroadcastStats):
            progress = (stats.sent + stats.failed + stats.blocked) / stats.total * 100

            try:
                await progress_msg.edit_text(
                    localize("broadcast.progress",
                             progress=progress,
                             sent=stats.sent,
                             total=stats.total,
                             failed=stats.failed,
                             time=int((datetime.now() - stats.start_time).total_seconds())),
                    reply_markup=_cancel_keyboard()
                )
            except (TelegramBadRequest, TelegramForbiddenError) as e:
                await log_audit("broadcast_progress_fail", level="WARNING", details=str(e))

        manager = BroadcastManager(bot=call.bot)
        broadcast_managers[admin_id] = manager

        stats = await manager.broadcast(
            user_ids=user_ids,
            text=safe_text,
            reply_markup=markup,
            photo=photo,
            parse_mode="HTML",
            progress_callback=update_progress
        )

        duration = int(stats.duration) if stats.duration else 0
        try:
            await progress_msg.edit_text(
                localize("broadcast.done",
                         total=stats.total,
                         sent=stats.sent,
                         failed=stats.failed,
                         blocked=stats.blocked,
                         success=f"{stats.success_rate:.1f}",
                         duration=duration),
                reply_markup=back("send_message")
            )
        except TelegramBadRequest as e:
            await log_audit("broadcast_final_edit_fail", level="WARNING", details=str(e))

        admin_name = caller_name(call)
        await log_audit("broadcast_sent", user_id=admin_id,
                        details=f"admin={admin_name}, delivered={stats.sent}/{stats.total}, duration={duration}s, has_photo={bool(photo)}")

    except Exception as e:
        await call.message.answer(
            localize("errors.invalid_data"),
            reply_markup=back("send_message")
        )
        await log_audit("broadcast_error", level="ERROR", user_id=admin_id, details=str(e))

    finally:
        broadcast_managers.pop(admin_id, None)
        await state.clear()


@router.callback_query(F.data == "cancel_broadcast", HasPermissionFilter(permission=Permission.BROADCAST))
async def cancel_broadcast_handler(call: CallbackQuery):
    """Cancel the caller's current mailing."""
    manager = broadcast_managers.get(call.from_user.id)
    if manager is not None:
        manager.cancel()
        await call.answer(localize("broadcast.cancel"), show_alert=True)
    else:
        await call.answer(localize("broadcast.warning"), show_alert=True)

