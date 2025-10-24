import React from "react";
import { NavLink, useNavigate } from "react-router-dom";
import { authAPI } from "../services/api";
import "./Navbar.css";

const Navbar = () => {
  const navigate = useNavigate();
  const user = JSON.parse(localStorage.getItem('user') || '{}');

  const handleLogout = async () => {
    try {
      await authAPI.logout();
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      navigate('/login');
    }
  };

  return (
    <nav className="navbar">
      <ul>
        <li>
          <NavLink
            to="/"
            end
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            Payment Posting
          </NavLink>
        </li>
        <li>
          <NavLink
            to="/manual-debit"
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            Manual Debit
          </NavLink>
        </li>
        <li>
          <NavLink
            to="/assessment-posting"
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            Assessment Posting
          </NavLink>
        </li>
        <li>
          <NavLink
            to="/penalty-posting"
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            Penalty Posting
          </NavLink>
        </li>
        <li>
          <NavLink
            to="/reports"
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            Reports
          </NavLink>
        </li>
        <li className="navbar-right">
          <span className="user-info">{user.name} ({user.role})</span>
          <button onClick={handleLogout} className="logout-btn">Logout</button>
        </li>
      </ul>
    </nav>
  );
};

export default Navbar;
