import streamlit as st
import random
import pandas as pd
from datetime import datetime

# Sample data for flavor
past_events = [
    "The first computer bug is found (an actual insect).",
    "A new dance craze sweeps the nation.",
    "Scientists discover a talking fish (it’s grumpy).",
]
future_events = [
    "Hoverboards finally work without catching fire.",
    "AI wins the World Hide-and-Seek Championship.",
    "Mars colony declares pizza the official food.",
]
past_tech = ["Telegraph", "Rotary phone", "Floppy disk"]
future_tech = ["Quantum communicator", "Brain-chip interface", "Anti-gravity boots"]

# Streamlit app
st.title("Time Travel Simulator ⏳")
st.markdown("Pick a year and jump into the past or future!")

# Year input
current_year = datetime.now().year
year = st.number_input("Enter a year", min_value=1800, max_value=2500, value=current_year, step=1)

# Simulate the time jump
if st.button("Travel Now!"):
    st.subheader(f"Welcome to {year}!")
    
    # Determine past or future
    is_future = year > current_year
    
    # Generate random scenario
    event = random.choice(future_events if is_future else past_events)
    tech = random.choice(future_tech if is_future else past_tech)
    population = random.randint(1_000_000, 20_000_000_000) if is_future else random.randint(100_000, 7_000_000_000)
    
    # Display results
    st.write(f"**Top News:** {event}")
    st.write(f"**Breakthrough Tech:** {tech}")
    st.write(f"**World Population:** {population:,}")
    
    # Random fun fact
    fun_facts = [
        f"{'Aliens' if is_future else 'A king'} made contact—accidentally.",
        f"People are obsessed with {'hover-pets' if is_future else 'penny-farthings'}.",
        f"The sky turned {'purple' if is_future else 'green'} for a week."
    ]
    st.write(f"**Weird Fact:** {random.choice(fun_facts)}")
    
    # Simple timeline visualization
    st.subheader("Your Time Jump")
    df = pd.DataFrame({
        "Years": [1800, current_year, year, 2500],
        "Era": ["Past", "Now", "You Are Here", "Far Future"]
    })
    st.line_chart(df.set_index("Years"))

# Add a retro/futuristic vibe
st.markdown(
    """
    <style>
    .stApp {
        background: linear-gradient(135deg, #1e1e2f, #3b1b4d);
        color: #e0e0ff;
        font-family: 'Courier New', monospace;
    }
    </style>
    """,
    unsafe_allow_html=True
)

st.markdown("*What’s next? Step into the unknown!*")