import webbrowser
import os
import streamlit as st

# Function to open PHP file
def open_php():
    file_path = os.path.abspath("signup.php")  # Get full path of signup.php
    webbrowser.open("file://" + file_path)  # Open in default browser

# Streamlit UI
st.title("Open signup.php")

# Button to open signup.php
if st.button("Open signup.php"):
    open_php()
