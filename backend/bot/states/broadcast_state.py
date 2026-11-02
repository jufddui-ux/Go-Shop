from aiogram.filters.state import StatesGroup, State


class BroadcastFSM(StatesGroup):
    """FSM states for the broadcast message"""
    waiting_message = State()
    waiting_custom_button = State()
    waiting_confirmation = State()
